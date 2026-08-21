<?php

declare(strict_types=1);

namespace App\Application\Actions\BacBottles;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\BacBottleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ViewBacBottleAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly BacBottleService $bacBottles,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        return $this->respondWithData($this->bacBottles->get($this->userPdo(), (string) $this->resolveArg('id')));
    }
}
