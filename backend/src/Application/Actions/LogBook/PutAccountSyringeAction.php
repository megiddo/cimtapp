<?php

declare(strict_types=1);

namespace App\Application\Actions\LogBook;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\FieldParser;
use App\Domain\LogBook\AccountSyringeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class PutAccountSyringeAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly AccountSyringeService $syringe,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $row = $this->syringe->put($this->userPdo(), FieldParser::from($this->getFormData()));

        return $this->respondWithData($row);
    }
}