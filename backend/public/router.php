<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in server (`composer start`).
 * Apache uses .htaccess instead.
 */
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/index.php';
    return true;
}

$html = __DIR__ . '/index.html';
if (is_file($html)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($html);
    return true;
}

require __DIR__ . '/index.php';
return true;
