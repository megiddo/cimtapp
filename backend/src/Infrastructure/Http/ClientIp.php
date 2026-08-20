<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ServerRequestInterface as Request;

final class ClientIp
{
    public static function from(Request $request): string
    {
        $params = $request->getServerParams();
        $ip = $params['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || $ip === '') {
            return 'unknown';
        }

        return $ip;
    }
}
