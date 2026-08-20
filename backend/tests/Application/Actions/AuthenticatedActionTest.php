<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\AuthenticatedAction;
use App\Domain\Auth\AuthContext;
use App\Domain\Auth\Session;
use App\Domain\Auth\User;
use App\Domain\Auth\UserStorePort;
use App\Domain\DomainException\DomainRecordNotFoundException;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\NullLogger;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Tests\TestCase;

class AuthenticatedActionTest extends TestCase
{
    public function testMissingAuthContextIs401(): void
    {
        $action = $this->makeAction($this->memoryStore());
        $request = $this->createRequest('GET', '/api/v1/me');
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $this->expectException(HttpUnauthorizedException::class);
        $action($request, $response, []);
    }

    public function testActionRunsInsideWithUnlocked(): void
    {
        $order = [];
        $store = new class ($order) implements UserStorePort {
            /** @param list<string> $order */
            public function __construct(private array &$order)
            {
            }

            public function create(string $userId, string $dek): void
            {
            }

            public function withUnlocked(string $userId, string $dek, callable $callback): mixed
            {
                $this->order[] = 'open';
                $result = $callback(new PDO('sqlite::memory:'));
                $this->order[] = 'close';

                return $result;
            }
        };

        $action = new class (new NullLogger(), $store, $order) extends AuthenticatedAction {
            /** @param list<string> $order */
            public function __construct(NullLogger $logger, UserStorePort $store, private array &$order)
            {
                parent::__construct($logger, $store);
            }

            protected function action(): Response
            {
                $this->order[] = $this->userPdo() instanceof PDO ? 'action' : 'missing';

                return $this->respondWithData(['ok' => true]);
            }
        };

        $request = $this->createRequest('GET', '/api/v1/me')
            ->withAttribute(AuthContext::class, $this->context());
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $result = $action($request, $response, []);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(['open', 'action', 'close'], $order);
    }

    public function testDomainNotFoundInsideUnlockBecomesHttpNotFound(): void
    {
        $action = new class (new NullLogger(), $this->memoryStore()) extends AuthenticatedAction {
            protected function action(): Response
            {
                throw new DomainRecordNotFoundException('missing');
            }
        };
        $request = $this->createRequest('GET', '/api/v1/me')
            ->withAttribute(AuthContext::class, $this->context());
        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $this->expectException(HttpNotFoundException::class);
        $action($request, $response, []);
    }

    private function makeAction(UserStorePort $store): AuthenticatedAction
    {
        return new class (new NullLogger(), $store) extends AuthenticatedAction {
            protected function action(): Response
            {
                return $this->respondWithData(['ok' => true]);
            }
        };
    }

    private function memoryStore(): UserStorePort
    {
        return new class implements UserStorePort {
            public function create(string $userId, string $dek): void
            {
            }

            public function withUnlocked(string $userId, string $dek, callable $callback): mixed
            {
                return $callback(new PDO('sqlite::memory:'));
            }
        };
    }

    private function context(): AuthContext
    {
        return new AuthContext(
            new User('11111111-1111-4111-8111-111111111111', 'a@b.c', 'h', null, 'c', 'n', 'now', null),
            str_repeat('k', 32),
            new Session(str_repeat('a', 64), '11111111-1111-4111-8111-111111111111', '2099-01-01T00:00:00Z', 'now'),
        );
    }
}
