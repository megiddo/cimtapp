<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\Action;
use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\AuthContext;
use App\Infrastructure\Persistence\UserStore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpUnauthorizedException;

final class ViewPreMigrationBackupAction extends Action
{
    public function __construct(
        LoggerInterface $logger,
        private readonly UserStore $userStore,
    ) {
        parent::__construct($logger);
    }

    protected function action(): Response
    {
        $context = $this->request->getAttribute(AuthContext::class);
        if (!$context instanceof AuthContext) {
            throw new HttpUnauthorizedException($this->request, AuthConfig::AUTH_REQUIRED);
        }

        return $this->respondWithData([
            'available' => $this->userStore->hasPreMigrationBackup($context->user->id),
        ]);
    }
}
