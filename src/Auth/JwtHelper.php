<?php

declare(strict_types=1);

namespace Laenutus\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laenutus\Config;
use UnexpectedValueException;

final class JwtHelper
{
    public static function encode(array $payload): string
    {
        $secret = Config::get('JWT_SECRET', 'dev-secret-change-me');
        $payload['iat'] = time();
        $payload['exp'] = time() + 86400;

        return JWT::encode($payload, $secret, 'HS256');
    }

    /** @return array<string, mixed> */
    public static function decode(string $token): array
    {
        $secret = Config::get('JWT_SECRET', 'dev-secret-change-me');

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (UnexpectedValueException $e) {
            throw new AuthException('INVALID_TOKEN', 'Vigane või aegunud token', 401);
        }
    }
}
