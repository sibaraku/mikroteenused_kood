<?php

declare(strict_types=1);

namespace Laenutus\Reservations;

use Laenutus\Auth\AuthException;
use PDO;
use Throwable;

final class ReservationsService
{
    public function __construct(
        private readonly ?PDO $db = null,
        private readonly ?NotificationsHttpClient $notifications = null,
    ) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    private function notifications(): NotificationsHttpClient
    {
        return $this->notifications ?? new NotificationsHttpClient();
    }

    /** @return list<array<string, mixed>> */
    public function list(string $userId): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM reservations WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $userId = (string) ($data['userId'] ?? '');
        $itemId = (string) ($data['itemId'] ?? '');
        $itemName = (string) ($data['itemName'] ?? '');
        $email = (string) ($data['email'] ?? '');
        if ($userId === '' || $itemId === '' || $itemName === '' || $email === '') {
            throw new AuthException('INVALID_INPUT', 'Kõik väljad on kohustuslikud', 400);
        }

        $stmt = $this->db()->prepare(
            "SELECT id FROM reservations WHERE user_id = :user_id AND item_id = :item_id AND status IN ('pending', 'notified') LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'item_id' => $itemId]);
        if ($stmt->fetch()) {
            throw new AuthException('RESERVATION_EXISTS', 'Olete selle vahendi juba ootejärjekorda lisanud', 409);
        }

        $id = generate_id('r');
        $stmt = $this->db()->prepare(
            "INSERT INTO reservations (id, user_id, item_id, item_name, email, status) VALUES (:id, :user_id, :item_id, :item_name, :email, 'pending')"
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'item_id' => $itemId,
            'item_name' => $itemName,
            'email' => $email,
        ]);

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM reservations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $reservation = $stmt->fetch();
        if (!$reservation) {
            throw new AuthException('NOT_FOUND', 'Broneeringut ei leitud', 404);
        }

        return $this->format($reservation);
    }

    public function cancel(string $id, string $userId): void
    {
        $stmt = $this->db()->prepare(
            "UPDATE reservations SET status = 'cancelled' WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'notified')"
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        if ($stmt->rowCount() === 0) {
            $reservation = $this->get($id);
            if ($reservation['userId'] !== $userId) {
                throw new AuthException('FORBIDDEN', 'Teil pole õigust seda broneeringut tühistada', 403);
            }
            throw new AuthException('RESERVATION_CLOSED', 'Broneeringut ei saa enam tühistada', 409);
        }
    }

    /** @return array<string, mixed>|null */
    public function notifyNext(string $itemId, ?string $requestId): ?array
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "SELECT * FROM reservations WHERE item_id = :item_id AND status = 'pending' ORDER BY created_at, id LIMIT 1 FOR UPDATE"
            );
            $stmt->execute(['item_id' => $itemId]);
            $reservation = $stmt->fetch();
            if (!$reservation) {
                $db->commit();
                return null;
            }

            $this->notifications()->create([
                'userId' => $reservation['user_id'],
                'reservationId' => $reservation['id'],
                'email' => $reservation['email'],
                'message' => sprintf('Vahend "%s" on nüüd saadaval. Saate selle laenutada.', $reservation['item_name']),
            ], $requestId);

            $stmt = $db->prepare("UPDATE reservations SET status = 'notified', notified_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $reservation['id']]);
            $db->commit();

            return $this->get($reservation['id']);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function format(array $row): array
    {
        return [
            'id' => $row['id'],
            'userId' => $row['user_id'],
            'itemId' => $row['item_id'],
            'itemName' => $row['item_name'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'] ?? null,
            'notifiedAt' => $row['notified_at'] ?? null,
        ];
    }
}