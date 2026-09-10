<?php

declare(strict_types=1);

namespace Laenutus\Notifications;

use Laenutus\Auth\AuthException;
use PDO;

final class NotificationsService
{
    public function __construct(private readonly ?PDO $db = null) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $stmt = $this->db()->query('SELECT * FROM notifications ORDER BY created_at DESC');

        return array_map([$this, 'format'], $stmt->fetchAll());
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, string $requestId): array
    {
        $userId = (string) ($data['userId'] ?? '');
        $loanId = (string) ($data['loanId'] ?? '');
        $email = (string) ($data['email'] ?? '');
        $message = (string) ($data['message'] ?? '');

        if ($userId === '' || $loanId === '' || $email === '' || $message === '') {
            throw new AuthException('INVALID_INPUT', 'Kõik väljad on kohustuslikud', 400);
        }

        $id = generate_id('n');

        $stmt = $this->db()->prepare(
            'INSERT INTO notifications (id, user_id, loan_id, email, message, status, sent_at)
             VALUES (:id, :user_id, :loan_id, :email, :message, :status, NOW())'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
            'loan_id' => $loanId,
            'email' => $email,
            'message' => $message,
            'status' => 'sent',
        ]);

        laenutus_log('info', 'Notification sent (simulated)', $requestId, [
            'notificationId' => $id,
            'email' => $email,
            'loanId' => $loanId,
        ]);

        return $this->format([
            'id' => $id,
            'user_id' => $userId,
            'loan_id' => $loanId,
            'email' => $email,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function format(array $row): array
    {
        return [
            'id' => $row['id'],
            'userId' => $row['user_id'],
            'loanId' => $row['loan_id'],
            'email' => $row['email'],
            'message' => $row['message'],
            'status' => $row['status'],
            'sentAt' => $row['sent_at'] ?? null,
        ];
    }
}
