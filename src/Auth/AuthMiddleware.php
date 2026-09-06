<?php

declare(strict_types=1);

namespace Laenutus\Auth;

use Laenutus\Request;

final class AuthMiddleware
{
    /** @return array{userId: string, role: string, email: string} */
    public static function requireAuth(Request $request): array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new AuthException('UNAUTHORIZED', 'Autentimine on nõutud', 401);
        }

        $payload = JwtHelper::decode($token);
        if (!isset($payload['userId'], $payload['role'])) {
            throw new AuthException('INVALID_TOKEN', 'Vigane token', 401);
        }

        return [
            'userId' => (string) $payload['userId'],
            'role' => (string) $payload['role'],
            'email' => (string) ($payload['email'] ?? ''),
        ];
    }

    /** @param array{userId: string, role: string} $auth */
    public static function requireAdmin(array $auth): void
    {
        if ($auth['role'] !== 'admin') {
            throw new AuthException('FORBIDDEN', 'Ainult administraatoril on õigus', 403);
        }
    }

    /** @param array{userId: string, role: string} $auth */
    public static function canAccessLoan(array $auth, string $loanUserId): void
    {
        if ($auth['role'] === 'admin') {
            return;
        }

        if ($auth['userId'] !== $loanUserId) {
            throw new AuthException('FORBIDDEN', 'Teil pole õigust seda laenutust vaadata', 403);
        }
    }
}
