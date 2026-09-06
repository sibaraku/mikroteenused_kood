<?php

declare(strict_types=1);

namespace Laenutus\Auth;

use Laenutus\Database;
use PDO;

final class AuthService
{
    public function __construct(private readonly ?PDO $db = null) {}

    private function db(): PDO
    {
        return $this->db ?? Database::connection();
    }

    /** @return array{token: string, user: array<string, mixed>} */
    public function login(string $email, string $password): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, email, password_hash, role, name FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new AuthException('INVALID_CREDENTIALS', 'Vale e-post või parool', 401);
        }

        $token = JwtHelper::encode([
            'userId' => $user['id'],
            'role' => $user['role'],
            'email' => $user['email'],
        ]);

        return [
            'token' => $token,
            'user' => $this->publicUser($user),
        ];
    }

    /** @return array<string, mixed> */
    public function me(string $userId): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id, email, role, name FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new AuthException('USER_NOT_FOUND', 'Kasutajat ei leitud', 404);
        }

        return $this->publicUser($user);
    }

    /** @param array<string, mixed> $user */
    private function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => $user['name'],
        ];
    }
}
