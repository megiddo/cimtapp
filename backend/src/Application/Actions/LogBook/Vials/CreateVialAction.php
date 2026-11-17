<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook\Vials;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\FieldParser;
use App\Domain\LogBook\VialService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class CreateVialAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly VialService $vials,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $created = $this->vials->create($this->userPdo(), FieldParser::from($this->getFormData()));

        return $this->respondWithData($created, 201);
    }
}