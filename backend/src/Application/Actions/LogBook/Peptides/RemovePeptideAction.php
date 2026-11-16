<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook\Peptides;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\LogBook\LogPeptideService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class RemovePeptideAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly LogPeptideService $peptides,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $row = $this->peptides->remove($this->userPdo(), (string) $this->resolveArg('id'));

        return $this->respondWithData($row);
    }
}