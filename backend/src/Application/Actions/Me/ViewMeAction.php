<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\CompoundService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ViewMeAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly AuthService $auth,
        private readonly CompoundService $compounds,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $me = $this->auth->meFromUserDb($this->userPdo());
        $me['remainder'] = $this->compounds->currentRemainder($this->userPdo());

        return $this->respondWithData($me);
    }
}
