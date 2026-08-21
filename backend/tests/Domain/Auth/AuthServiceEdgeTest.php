<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\GoogleUserInfo;
use App\Domain\Crypto\Crypto;
use PDO;
use Tests\TestCase;

class AuthServiceEdgeTest extends TestCase
{
    public function testMeFromUserDbReadsSnapshotAndRejectsEmpty(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $auth = $container->get(AuthService::class);

        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE account (
                user_id TEXT, email TEXT, password_hash TEXT, google_sub TEXT, updated_at TEXT
            )'
        );

        $this->expectException(\App\Domain\Auth\AuthenticationException::class);
        $auth->meFromUserDb($pdo);
    }

    public function testMeFromUserDbHappyPath(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $auth = $container->get(AuthService::class);
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE account (
                user_id TEXT, email TEXT, password_hash TEXT, google_sub TEXT, updated_at TEXT
            )'
        );
        $pdo->exec("INSERT INTO account VALUES ('u', 'a@b.c', 'hash', NULL, 'now')");
        $me = $auth->meFromUserDb($pdo);
        $this->assertSame('a@b.c', $me['email']);
        $this->assertTrue($me['has_password']);
        $this->assertFalse($me['has_google']);
        $this->assertNull($me['remainder']);
    }

    public function testGoogleIncompleteProfileFails(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $auth = $container->get(AuthService::class);
        $this->expectException(\App\Domain\Auth\GoogleOAuthException::class);
        $auth->loginWithGoogle(new GoogleUserInfo('', 'a@b.c', true));
    }

    public function testGoogleSubWithoutMatchingEmailFails(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('orphan-sub', 'first@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $this->globalPdo($app)->exec("UPDATE users SET email = 'moved@example.com'");

        $container = $app->getContainer();
        $this->assertNotNull($container);
        $this->expectException(\App\Domain\Auth\GoogleAccountConflictException::class);
        $container->get(AuthService::class)->loginWithGoogle(
            new GoogleUserInfo('orphan-sub', 'other@example.com', true)
        );
    }

    public function testOrphanedSessionIs401(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'gone@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);
        $this->globalPdo($app)->exec('PRAGMA foreign_keys = OFF');
        $this->globalPdo($app)->exec('DELETE FROM users');
        $me = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(401, $me->getStatusCode());
    }

    public function testUniqueEmailViolationIsMappedTo422(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'race@example.com',
            'password' => 'twelvechars!!',
        ]));
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $users = $container->get(\App\Domain\Auth\UserRepository::class);
        $crypto = $container->get(Crypto::class);
        $dek = $crypto->mintDek();
        $wrapped = $crypto->wrapDek($dek);
        try {
            $users->insert(new \App\Domain\Auth\User(
                '22222222-2222-4222-8222-222222222222',
                'race@example.com',
                'hash',
                null,
                $wrapped->ciphertext(),
                $wrapped->nonce(),
                'now',
                null,
            ));
            $this->fail('expected unique violation');
        } catch (\PDOException $e) {
            $this->assertTrue($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE'));
        }
    }

    public function testTransactionalRollsBack(): void
    {
        $app = $this->getAppInstance();
        $users = $app->getContainer()?->get(\App\Domain\Auth\UserRepository::class);
        $this->assertNotNull($users);
        try {
            $users->transactional(static function (): void {
                throw new \RuntimeException('boom');
            });
            $this->fail('expected boom');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }
    }

    public function testGoogleInvalidEmailFails(): void
    {
        $app = $this->getAppInstance();
        $auth = $app->getContainer()?->get(AuthService::class);
        $this->assertNotNull($auth);
        $this->expectException(\App\Domain\Auth\GoogleOAuthException::class);
        $auth->loginWithGoogle(new GoogleUserInfo('sub', 'not-an-email', true));
    }

    public function testExistingGoogleSubSameAccountLogsIn(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('same-sub', 'same@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $again = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $this->assertSame(302, $again->getStatusCode());
        $this->assertSame('http://localhost:24780/', $again->getHeaderLine('Location'));
        $this->assertSame(1, (int) $this->globalPdo($app)->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
