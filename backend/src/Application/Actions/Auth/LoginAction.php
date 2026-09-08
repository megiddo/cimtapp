<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthRateLimiter;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\SessionIssuer;
use App\Domain\Auth\UserMeMapper;
use App\Infrastructure\Http\ClientIp;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class LoginAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly AuthService $auth,
        private readonly SessionIssuer $sessions,
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
        $response = $this->respondWithData(UserMeMapper::fromUser($user));

        return $this->sessions->issue($response, $user->id);
    }
}
