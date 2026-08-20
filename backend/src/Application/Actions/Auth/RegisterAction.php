<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\SessionService;
use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class RegisterAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly SessionCookie $cookie,
        private readonly CredentialParser $parser,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $credentials = $this->parser->parse($this->getFormData());
        $user = $this->auth->register($credentials['email'], $credentials['password']);
        $session = $this->sessions->create($user->id);
        $response = $this->respondWithData($user->toMeArray(), 201);

        return $this->cookie->apply($response, $session->id, $this->sessions->ttlSeconds());
    }
}
