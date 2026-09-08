<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\Action;
use App\Domain\Auth\RequireAuthContext;
use App\Domain\Auth\UserStorePort;
use App\Domain\DomainException\DomainRecordNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

/**
 * Puts the pre-migration .enc.bak back as the live store. Does not unlock:
 * AuthenticatedAction would re-encrypt the already-migrated plaintext over it.
 */
final class RestorePreMigrationBackupAction extends Action
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
        $userId = $context->user->id;
        if (!$this->userStore->hasPreMigrationBackup($userId)) {
            throw new DomainRecordNotFoundException('No pre-migration backup.');
        }

        $this->userStore->restorePreMigrationBackup($userId);

        return $this->respondWithData(['ok' => true]);
    }
}
