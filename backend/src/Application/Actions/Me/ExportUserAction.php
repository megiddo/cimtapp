<?php

declare(strict_types=1);

namespace App\Application\Actions\Me;

use App\Application\Actions\Action;
use App\Domain\Auth\RequireAuthContext;
use App\Domain\Auth\UserStorePort;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

/**
 * Authenticated download of the user's plaintext sqlite. Temp files are shredded
 * before the response is returned; the body is the exfil unit.
 */
final class ExportUserAction extends Action
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
        $bytes = $this->userStore->exportPlaintext($context->user->id, $context->dek);
        $this->response->getBody()->write($bytes);

        return $this->response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename="peptrack-export.sqlite"')
            ->withHeader('Cache-Control', 'no-store')
            ->withStatus(200);
    }
}
