<?php

declare(strict_types=1);

namespace App\Application\Actions\BacBottles;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\BacBottleService;
use App\Domain\Dose\FieldParser;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class CreateBacBottleAction extends AuthenticatedAction
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
        $created = $this->bacBottles->create($this->userPdo(), FieldParser::from($this->getFormData()));

        return $this->respondWithData($created, 201);
    }
}
