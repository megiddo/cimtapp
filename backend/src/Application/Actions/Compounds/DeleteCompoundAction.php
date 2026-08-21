<?php

declare(strict_types=1);

namespace App\Application\Actions\Compounds;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\CompoundService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class DeleteCompoundAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly CompoundService $compounds,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $this->compounds->delete($this->userPdo(), (string) $this->resolveArg('id'));

        return $this->respondWithData(null, 204);
    }
}
