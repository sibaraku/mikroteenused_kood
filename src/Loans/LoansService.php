<?php

declare(strict_types=1);

namespace Laenutus\Loans;

use Laenutus\Auth\AuthException;
use Laenutus\Items\ItemsService;
use Laenutus\Notifications\NotificationsService;
use PDO;
use PDOException;

final class LoansService
{
    public function __construct(
        private readonly ?PDO $db = null,
        private readonly ?ItemsService $items = null,
        private readonly ?NotificationsService $notifications = null,
    ) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    private function items(): ItemsService
    {
        return $this->items ?? new ItemsService($this->db());
    }

    private function notifications(): NotificationsService
    {
        return $this->notifications ?? new NotificationsService($this->db());
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
    public function create(array $data, string $requestId): array
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

        $item = $this->items()->findRaw($itemId);
        if ($item === null) {
            throw new AuthException('ITEM_NOT_FOUND', 'Vahendit ei leitud', 404);
        }

        $status = 'pending_item';
        if ($item['status'] === 'available') {
            $status = 'confirmed';
        } elseif (in_array($item['status'], ['broken', 'maintenance'], true)) {
            throw new AuthException('ITEM_UNAVAILABLE', 'Vahend pole laenutamiseks saadaval', 400);
        } elseif ($item['status'] === 'reserved') {
            throw new AuthException('ITEM_UNAVAILABLE', 'Vahend on juba broneeritud', 400);
        }

        $loanId = 'l-' . bin2hex(random_bytes(4));

        $this->db()->beginTransaction();

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

            if ($status === 'confirmed') {
                $this->items()->reserve($itemId);
            }

            $userEmail = $this->getUserEmail($userId);
            $message = sprintf(
                'Teie laenutus %s vahendile %s on %s.',
                $loanId,
                $item['name'],
                $status === 'confirmed' ? 'kinnitatud' : 'ootel'
            );
            $this->notifications()->createAndSend($userId, $loanId, $userEmail, $message);

            $this->db()->commit();
        } catch (PDOException $e) {
            $this->db()->rollBack();
            laenutus_log('error', 'Loan creation failed', $requestId, ['error' => $e->getMessage()]);
            throw new AuthException('INTERNAL_ERROR', 'Laenutuse loomine ebaõnnestus', 500);
        }

        laenutus_log('info', 'Loan created', $requestId, ['loanId' => $loanId, 'status' => $status]);

        return $this->get($loanId);
    }

    /** @param array<string, mixed> $data */
    public function update(string $id, array $data): array
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
            $this->items()->release($loan['item_id']);
        }

        return $this->get($id);
    }

    public function delete(string $id): void
    {
        $loan = $this->findRaw($id);
        if ($loan === null) {
            throw new AuthException('NOT_FOUND', 'Laenutust ei leitud', 404);
        }

        if ($loan['status'] === 'confirmed') {
            $this->items()->release($loan['item_id']);
        }

        $stmt = $this->db()->prepare('DELETE FROM loans WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function getUserEmail(string $userId): string
    {
        $stmt = $this->db()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row['email'] ?? 'unknown@kool.ee';
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
