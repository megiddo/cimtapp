<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\AuthContext;
use App\Domain\Auth\UserStorePort;
use App\Domain\DomainException\DomainRecordNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Puts the pre-migration .enc.bak back as the live store. Does not unlock:
 * AuthenticatedAction would re-encrypt the already-migrated plaintext over it.
 */
final class RestorePreMigrationBackupAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly UserStorePort $userStore,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $context = $this->request->getAttribute(AuthContext::class);
        if (!$context instanceof AuthContext) {
            throw new HttpUnauthorizedException($this->request, AuthConfig::AUTH_REQUIRED);
        }

        $userId = $context->user->id;
        if (!$this->userStore->hasPreMigrationBackup($userId)) {
            throw new DomainRecordNotFoundException('No pre-migration backup.');
        }

        $this->userStore->restorePreMigrationBackup($userId);

        return $this->respondWithData(['ok' => true]);
    }
}
