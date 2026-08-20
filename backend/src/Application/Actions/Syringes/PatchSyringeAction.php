<?php

declare(strict_types=1);

namespace App\Application\Actions\Syringes;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\UserStorePort;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\SyringeService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class PatchSyringeAction extends AuthenticatedAction
{
    public function __construct(
        LoggerInterface $logger,
        UserStorePort $userStore,
        private readonly SyringeService $syringes,
    ) {
        parent::__construct($logger, $userStore);
    }

    protected function action(): Response
    {
        $id = (string) $this->resolveArg('id');
        $updated = $this->syringes->patch($this->userPdo(), $id, FieldParser::from($this->getFormData()));

        return $this->respondWithData($updated);
    }
}
