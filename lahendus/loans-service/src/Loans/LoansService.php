<?php

declare(strict_types=1);

namespace Laenutus\Loans;

use Laenutus\Auth\AuthException;
use Laenutus\Auth\AuthService;
use Laenutus\Clients\ItemsHttpClient;
use Laenutus\Clients\NotificationsHttpClient;
use PDO;
use PDOException;
use Throwable;

final class LoansService
{
    public function __construct(
        private readonly ?PDO $db = null,
        private readonly ?ItemsHttpClient $items = null,
        private readonly ?NotificationsHttpClient $notifications = null,
        private readonly ?AuthService $auth = null,
    ) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    private function items(): ItemsHttpClient
    {
        return $this->items ?? new ItemsHttpClient();
    }

    private function notifications(): NotificationsHttpClient
    {
        return $this->notifications ?? new NotificationsHttpClient();
    }

    private function auth(): AuthService
    {
        return $this->auth ?? new AuthService($this->db());
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $userId = null, bool $isAdmin = false): array
    {
        if ($isAdmin) {
            $stmt = $this->db()->query('SELECT * FROM loans ORDER BY created_at DESC');
        } else {
            $stmt = $this->db()->prepare('SELECT * FROM loans WHERE user_id = :user_id ORDER BY created_at DESC');
            $stmt->execute(['user_id' => $userId]);
        }

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $loan = $this->findRaw($id);
        if ($loan === null) {
            throw new AuthException('NOT_FOUND', 'Laenutust ei leitud', 404);
        }

        return $this->format($loan);
    }

    /** @return array<string, mixed>|null */
    public function findRaw(string $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM loans WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $loan = $stmt->fetch();

        return $loan ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $requestId, ?string $authToken = null): array
    {
        $userId = (string) ($data['userId'] ?? '');
        $itemId = (string) ($data['itemId'] ?? '');
        $startDate = (string) ($data['startDate'] ?? '');
        $endDate = (string) ($data['endDate'] ?? '');

        if ($userId === '' || $itemId === '' || $startDate === '' || $endDate === '') {
            throw new AuthException('INVALID_INPUT', 'Kõik väljad on kohustuslikud', 400);
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            throw new AuthException('INVALID_DATE', 'Kuupäev peab olema kujul YYYY-MM-DD', 400);
        }

        if ($endDate < $startDate) {
            throw new AuthException('INVALID_DATE_RANGE', 'Lõppkuupäev ei tohi olla enne alguskuupäeva', 400);
        }

        $item = $this->items()->get($itemId, $authToken, $requestId);

        $status = 'pending_item';
        if ($item['status'] === 'available') {
            $status = 'confirmed';
        } elseif (in_array($item['status'], ['broken', 'maintenance'], true)) {
            throw new AuthException('ITEM_UNAVAILABLE', 'Vahend pole laenutamiseks saadaval', 400);
        } elseif ($item['status'] === 'reserved') {
            throw new AuthException('ITEM_UNAVAILABLE', 'Vahend on juba broneeritud', 400);
        }

        $loanId = generate_id('l');

        try {
            $stmt = $this->db()->prepare(
                'INSERT INTO loans (id, user_id, item_id, start_date, end_date, status)
                 VALUES (:id, :user_id, :item_id, :start_date, :end_date, :status)'
            );
            $stmt->execute([
                'id' => $loanId,
                'user_id' => $userId,
                'item_id' => $itemId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ]);
        } catch (PDOException $e) {
            laenutus_log('error', 'Loan creation failed', $requestId, ['error' => $e->getMessage()]);
            throw new AuthException('INTERNAL_ERROR', 'Laenutuse loomine ebaõnnestus', 500);
        }

        if ($status === 'confirmed') {
            try {
                $this->items()->reserve($itemId, $authToken, $requestId);
            } catch (Throwable $e) {
                $this->compensateCancelled($loanId, $requestId);
                laenutus_log('warn', 'Reserve failed, loan cancelled', $requestId, ['loanId' => $loanId]);
                throw $e;
            }
        }

        $userEmail = $this->auth()->getUserEmail($userId);
        $message = sprintf(
            'Teie laenutus %s vahendile %s on %s.',
            $loanId,
            $item['name'],
            $status === 'confirmed' ? 'kinnitatud' : 'ootel'
        );

        try {
            $this->notifications()->create([
                'userId' => $userId,
                'loanId' => $loanId,
                'email' => $userEmail,
                'message' => $message,
            ], $requestId);
        } catch (Throwable $e) {
            laenutus_log('error', 'Notification failed, loan kept', $requestId, [
                'loanId' => $loanId,
                'error' => $e->getMessage(),
            ]);
        }

        laenutus_log('info', 'Loan created', $requestId, ['loanId' => $loanId, 'status' => $status]);

        return $this->get($loanId);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data, ?string $authToken = null, ?string $requestId = null): array
    {
        $loan = $this->findRaw($id);
        if ($loan === null) {
            throw new AuthException('NOT_FOUND', 'Laenutust ei leitud', 404);
        }

        if (!isset($data['status'])) {
            return $this->format($loan);
        }

        $valid = ['pending_item', 'confirmed', 'rejected', 'cancelled'];
        if (!in_array($data['status'], $valid, true)) {
            throw new AuthException('INVALID_STATUS', 'Vigane laenutuse staatus', 400);
        }

        $stmt = $this->db()->prepare('UPDATE loans SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $data['status'], 'id' => $id]);

        if ($data['status'] === 'cancelled' && $loan['status'] === 'confirmed') {
            $this->items()->release($loan['item_id'], $authToken, $requestId);
        }

        return $this->get($id);
    }

    public function delete(string $id, ?string $authToken = null, ?string $requestId = null): void
    {
        $loan = $this->findRaw($id);
        if ($loan === null) {
            throw new AuthException('NOT_FOUND', 'Laenutust ei leitud', 404);
        }

        if ($loan['status'] === 'confirmed') {
            $this->items()->release($loan['item_id'], $authToken, $requestId);
        }

        $stmt = $this->db()->prepare('DELETE FROM loans WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function compensateCancelled(string $loanId, string $requestId): void
    {
        $stmt = $this->db()->prepare("UPDATE loans SET status = 'cancelled' WHERE id = :id");
        $stmt->execute(['id' => $loanId]);
        laenutus_log('info', 'Compensation applied', $requestId, ['loanId' => $loanId, 'action' => 'cancelled']);
    }

    /** @param array<string, mixed> $row */
    private function format(array $row): array
    {
        return [
            'id' => $row['id'],
            'status' => $row['status'],
            'userId' => $row['user_id'],
            'itemId' => $row['item_id'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
        ];
    }
}
