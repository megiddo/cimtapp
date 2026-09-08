<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\Action;
use App\Domain\Auth\RequireAuthContext;
use App\Domain\Auth\UserStorePort;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class ViewPreMigrationBackupAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly UserStorePort $userStore,
        private readonly RequireAuthContext $auth,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $context = $this->auth->from($this->request);

        return $this->respondWithData([
            'available' => $this->userStore->hasPreMigrationBackup($context->user->id),
        ]);
    }
}
