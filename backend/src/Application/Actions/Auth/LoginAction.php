<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthRateLimiter;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\SessionService;
use App\Infrastructure\Http\ClientIp;
use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class LoginAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly SessionCookie $cookie,
        private readonly CredentialParser $parser,
        private readonly AuthRateLimiter $limiter,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $credentials = $this->parser->parse($this->getFormData());
        $this->limiter->guardLogin(ClientIp::from($this->request), $credentials['email']);
        $user = $this->auth->login($credentials['email'], $credentials['password']);
        $session = $this->sessions->create($user->id);
        $response = $this->respondWithData($user->toMeArray());

        return $this->cookie->apply($response, $session->id, $this->sessions->ttlSeconds());
    }
}
