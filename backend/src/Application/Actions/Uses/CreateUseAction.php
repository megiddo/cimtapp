<?php

declare(strict_types=1);

namespace App\Application\Actions\Uses;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\UseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class CreateUseAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly UseService $uses,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $created = $this->uses->create($this->userPdo(), FieldParser::from($this->getFormData()));

        return $this->respondWithData($created, 201);
    }
}
