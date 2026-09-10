<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/shared/bootstrap.php';

use Laenutus\Config;

Config::load(dirname(__DIR__) . '/.env');

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
