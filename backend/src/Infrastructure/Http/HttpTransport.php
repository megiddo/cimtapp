<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

interface HttpTransport
{
    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): string;
}
