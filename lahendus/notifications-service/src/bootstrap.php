<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/shared/bootstrap.php';

use Laenutus\Config;

Config::load(dirname(__DIR__) . '/.env');
