<?php

declare(strict_types=1);

namespace App\Application\Actions\Auth;

use App\Application\Actions\Action;
use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\OauthStateService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class GoogleStartAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly GoogleOAuthClient $google,
        private readonly OauthStateService $oauthStates,
        private readonly IdGenerator $ids,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        if (!$this->google->isConfigured()) {
            $error = new ActionError(ActionError::SERVICE_UNAVAILABLE, AuthConfig::GOOGLE_UNAVAILABLE);

            return $this->respond(new ActionPayload(503, null, $error));
        }

        $verifier = $this->ids->pkceVerifier();
        $state = $this->oauthStates->issue($verifier);
        $location = $this->google->authorizationUrl($state->state, $this->ids->pkceChallenge($verifier));

        return $this->response
            ->withHeader('Location', $location)
            ->withStatus(302);
    }
}
