<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Domain\Auth\SessionService;
use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class LogoutAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly SessionService $sessions,
        private readonly SessionCookie $cookie,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $sessionId = $this->cookie->read($this->request);
        if ($sessionId !== null) {
            $this->sessions->destroy($sessionId);
        }

        $response = $this->respondWithData(['ok' => true]);

        return $this->cookie->expire($response);
    }
}
