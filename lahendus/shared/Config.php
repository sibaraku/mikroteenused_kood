<?php

declare(strict_types=1);

namespace Laenutus;

final class Config
{
    private static array $config = [];

    public static function load(string $envPath): void
    {
        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            self::$config[trim($key)] = trim($value, " \t\"'");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$config[$key] ?? $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}
