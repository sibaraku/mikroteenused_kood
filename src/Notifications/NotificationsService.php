<?php

declare(strict_types=1);

namespace Laenutus\Notifications;

use PDO;

final class NotificationsService
{
    public function __construct(private readonly ?PDO $db = null) {}

    private function db(): PDO
    {
        return $this->db ?? \Laenutus\Database::connection();
    }

    public function createAndSend(string $userId, string $loanId, string $email, string $message): string
    {
        $id = 'n-' . bin2hex(random_bytes(4));

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

        laenutus_log('info', 'Notification sent (simulated)', 'system', [
            'notificationId' => $id,
            'email' => $email,
            'loanId' => $loanId,
        ]);

        return $id;
    }
}
