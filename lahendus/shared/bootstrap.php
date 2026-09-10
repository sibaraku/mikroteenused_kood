<?php

declare(strict_types=1);

function laenutus_log(string $level, string $message, string $requestId, array $context = []): void
{
    $payload = array_merge(['requestId' => $requestId], $context);
    error_log(sprintf('[laenutus][%s][%s] %s %s', $level, $requestId, $message, json_encode($payload)));
}

function generate_id(string $prefix): string
{
    return $prefix . '-' . bin2hex(random_bytes(4));
}
