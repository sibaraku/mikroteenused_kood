<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Laenutus\Config;

Config::load(dirname(__DIR__) . '/.env');

function laenutus_log(string $level, string $message, string $requestId, array $context = []): void
{
    $payload = array_merge(['requestId' => $requestId], $context);
    error_log(sprintf('[laenutus][%s][%s] %s %s', $level, $requestId, $message, json_encode($payload)));
}

function laenutus_view(string $name, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    include dirname(__DIR__) . '/views/' . $name . '.php';
    return (string) ob_get_clean();
}

function laenutus_render(string $view, array $data = []): void
{
    $content = laenutus_view($view, $data);
    echo laenutus_view('layout', array_merge($data, ['content' => $content]));
}

function generate_id(string $prefix): string
{
    return $prefix . '-' . bin2hex(random_bytes(4));
}
