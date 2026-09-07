<?php

declare(strict_types=1);

namespace App\Application\Actions\Profiles;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\ProfileService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ListProfilesAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly ProfileService $profiles,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        return $this->respondWithData($this->profiles->list($this->userPdo()));
    }
}
