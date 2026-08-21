<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\AuthContext;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\UserStorePort;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class SetPasswordAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly AuthService $auth,
        private readonly CredentialParser $parser,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $credentials = $this->parser->parse($this->getFormData());
        /** @var AuthContext $context */
        $context = $this->request->getAttribute(AuthContext::class);
        $user = $this->auth->setPassword($context->user, $credentials['password'], $this->userPdo());

        return $this->respondWithData($user->toMeArray());
    }
}
