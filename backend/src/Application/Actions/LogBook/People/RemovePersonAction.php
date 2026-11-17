<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook\People;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\LogBook\PersonService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class RemovePersonAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly PersonService $people,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $row = $this->people->remove($this->userPdo(), (string) $this->resolveArg('id'));

        return $this->respondWithData($row);
    }
}