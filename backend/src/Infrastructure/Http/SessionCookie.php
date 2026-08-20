<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Auth\AuthConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SessionCookie
{
    public function __construct(private readonly bool $secure)
    {
    }

    public function read(Request $request): ?string
    {
        $cookies = $request->getCookieParams();
        $value = $cookies[AuthConfig::SESSION_COOKIE] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function apply(Response $response, string $sessionId, int $maxAge): Response
    {
        return $response->withAddedHeader('Set-Cookie', $this->header($sessionId, $maxAge, false));
    }

    public function expire(Response $response): Response
    {
        return $response->withAddedHeader('Set-Cookie', $this->header('', 0, true));
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    private function header(string $value, int $maxAge, bool $expired): string
    {
        $parts = [
            AuthConfig::SESSION_COOKIE . '=' . $value,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=' . ($expired ? 0 : $maxAge),
        ];
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($expired) {
            $parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
        }

        return implode('; ', $parts);
    }
}
