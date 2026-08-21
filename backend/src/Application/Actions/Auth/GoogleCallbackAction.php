<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\GoogleAccountConflictException;
use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\OauthStateService;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\UnverifiedGoogleEmailException;
use App\Domain\Auth\ValidationException;
use App\Infrastructure\Http\SessionCookie;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class GoogleCallbackAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly GoogleOAuthClient $google,
        private readonly OauthStateService $oauthStates,
        private readonly AuthService $auth,
        private readonly SessionService $sessions,
        private readonly SessionCookie $cookie,
        private readonly string $appUrl,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $query = $this->request->getQueryParams();
        $code = isset($query['code']) && is_string($query['code']) ? $query['code'] : '';
        $state = isset($query['state']) && is_string($query['state']) ? $query['state'] : '';
        $oauthError = isset($query['error']) && is_string($query['error']) ? $query['error'] : '';

        if ($oauthError !== '' || $code === '' || $state === '') {
            return $this->redirectToLogin();
        }

        $stored = $this->oauthStates->consume($state);
        if ($stored === null) {
            return $this->redirectToLogin();
        }

        try {
            $profile = $this->google->fetchUser($code, $stored->codeVerifier);
            $user = $this->auth->loginWithGoogle($profile);
        } catch (UnverifiedGoogleEmailException | GoogleAccountConflictException | GoogleOAuthException | ValidationException) {
            return $this->redirectToLogin();
        }

        $session = $this->sessions->create($user->id);
        $response = $this->redirectToApp();

        return $this->cookie->apply($response, $session->id, $this->sessions->ttlSeconds());
    }

    private function redirectToLogin(): Response
    {
        return $this->response
            ->withHeader('Location', $this->appUrl . '/login?error=google')
            ->withStatus(302);
    }

    private function redirectToApp(): Response
    {
        return $this->response
            ->withHeader('Location', $this->appUrl . '/')
            ->withStatus(302);
    }
}
