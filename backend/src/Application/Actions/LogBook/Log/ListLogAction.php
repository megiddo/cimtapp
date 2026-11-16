<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook\Log;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\FieldParser;
use App\Domain\LogBook\LogEntryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ListLogAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly LogEntryService $log,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $query = FieldParser::from($this->request->getQueryParams());

        return $this->respondWithData($this->log->list($this->userPdo(), $query));
    }
}