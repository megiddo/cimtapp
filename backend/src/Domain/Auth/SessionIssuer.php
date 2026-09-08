<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;

final class SessionIssuer
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly SessionCookie $cookie,
    ) {
    }

    public function issue(Response $response, string $userId): Response
    {
        $session = $this->sessions->create($userId);

        return $this->cookie->apply($response, $session->id, $this->sessions->ttlSeconds());
    }
}
