<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\AuthContext;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\UserRepository;
use App\Domain\Crypto\Crypto;
use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Session cookie → load session → load user → unwrap DEK. Does not open the user store.
 * AuthenticatedAction opens withUnlocked around the HTTP handler.
 */
final class SessionAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly UserRepository $users,
        private readonly Crypto $crypto,
        private readonly SessionCookie $cookie,
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $sessionId = $this->cookie->read($request);
        if ($sessionId === null) {
            throw new HttpUnauthorizedException($request, AuthConfig::AUTH_REQUIRED);
        }

        $session = $this->sessions->loadValid($sessionId);
        if ($session === null) {
            throw new HttpUnauthorizedException($request, AuthConfig::AUTH_REQUIRED);
        }

        $user = $this->users->findById($session->userId);
        if ($user === null) {
            $this->sessions->destroy($sessionId);
            throw new HttpUnauthorizedException($request, AuthConfig::AUTH_REQUIRED);
        }

        $dek = $this->crypto->unwrapDek($user->dekNonce, $user->encryptedDek);
        $session = $this->sessions->touch($session);

        $request = $request->withAttribute(
            AuthContext::class,
            new AuthContext($user, $dek, $session)
        );

        return $handler->handle($request);
    }
}
