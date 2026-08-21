<?php

declare(strict_types=1);

namespace App\Application\Actions\Compounds;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\CompoundService;
use App\Domain\Dose\FieldParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class CreateCompoundAction extends AuthenticatedAction
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
        $created = $this->compounds->create($this->userPdo(), FieldParser::from($this->getFormData()));

        return $this->respondWithData($created, 201);
    }
}
