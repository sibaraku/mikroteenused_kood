<?php

declare(strict_types=1);

namespace Laenutus;

final class Response
{
    public static function json(array $data, int $status = 200, ?string $requestId = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($requestId !== null) {
            header('X-Request-Id: ' . $requestId);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public static function error(string $code, string $message, int $status, string $requestId): void
    {
        self::json([
            'code' => $code,
            'message' => $message,
            'requestId' => $requestId,
        ], $status, $requestId);
    }

    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
    }

    public static function noContent(string $requestId): void
    {
        http_response_code(204);
        header('X-Request-Id: ' . $requestId);
    }
}
