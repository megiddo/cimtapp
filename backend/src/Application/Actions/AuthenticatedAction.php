<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Domain\Auth\AuthContext;
use App\Domain\Auth\UserStorePort;
use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;

/**
 * Runs action() inside UserStore::withUnlocked so the PDO stays open for the handler.
 */
abstract class AuthenticatedAction extends Action
{
    protected PDO $userPdo;

    public function __construct(
        LoggerInterface $logger,
        protected UserStorePort $userStore,
    ) {
        parent::__construct($logger);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        $context = $request->getAttribute(AuthContext::class);
        if (!$context instanceof AuthContext) {
            throw new HttpUnauthorizedException($request, 'Authentication required.');
        }

        return $this->userStore->withUnlocked(
            $context->user->id,
            $context->dek,
            function (PDO $pdo) use ($request): Response {
                $this->userPdo = $pdo;
                $this->request = $request->withAttribute('userPdo', $pdo);
                try {
                    return $this->action();
                } catch (DomainRecordNotFoundException $e) {
                    throw new HttpNotFoundException($this->request, $e->getMessage());
                }
            }
        );
    }

    protected function userPdo(): PDO
    {
        return $this->userPdo;
    }
}
