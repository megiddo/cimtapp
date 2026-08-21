<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$envFile = dirname(__DIR__) . '/.env.testing';
if (is_file($envFile)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env.testing')->safeLoad();
}
