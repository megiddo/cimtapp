<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Me;

use App\Domain\Auth\AuthConfig;
use Tests\TestCase;

class StoreBackupActionTest extends TestCase
{
    public function testFreshAccountHasNoBackupAndRestoreIsNotFound(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'backup@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);

        $status = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me/store-backup',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $status->getStatusCode());
        $this->assertFalse($this->json($status)['data']['available']);

        $restore = $app->handle($this->createJsonRequest(
            'POST',
            '/api/v1/me/store-backup/restore',
            [],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(404, $restore->getStatusCode());
    }

    public function testRestorePutsBackupOverLiveStore(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'restore@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);

        $dataDir = $this->isolateDataDir();
        $users = glob($dataDir . '/users/*.sqlite.enc') ?: [];
        $this->assertCount(1, $users);
        $enc = $users[0];
        $before = hash_file('sha256', $enc);
        $this->assertNotFalse($before);
        copy($enc, $enc . '.bak');

        $profile = $this->json($app->handle($this->createRequest(
            'GET',
            '/api/v1/profiles',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        )))['data'][0];
        $renamed = $app->handle($this->createJsonRequest(
            'PATCH',
            '/api/v1/profiles/' . $profile['id'],
            ['name' => 'Pat'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $renamed->getStatusCode());
        $this->assertNotSame($before, hash_file('sha256', $enc));

        $available = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me/store-backup',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertTrue($this->json($available)['data']['available']);

        $restore = $app->handle($this->createJsonRequest(
            'POST',
            '/api/v1/me/store-backup/restore',
            [],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $restore->getStatusCode());
        $this->assertTrue($this->json($restore)['data']['ok']);
        $this->assertSame($before, hash_file('sha256', $enc));

        $after = $this->json($app->handle($this->createRequest(
            'GET',
            '/api/v1/profiles',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        )))['data'][0];
        $this->assertSame('Default', $after['name']);
    }

    public function testBackupRoutesRequireAuth(): void
    {
        $app = $this->getAppInstance();
        $this->assertSame(401, $app->handle($this->createRequest('GET', '/api/v1/me/store-backup'))->getStatusCode());
        $this->assertSame(401, $app->handle($this->createJsonRequest('POST', '/api/v1/me/store-backup/restore'))->getStatusCode());
    }

    public function testBackupActionsRejectMissingAuthContext(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $response = $app->getResponseFactory()->createResponse();

        $view = $container->get(\App\Application\Actions\Me\ViewPreMigrationBackupAction::class);
        $this->expectException(\Slim\Exception\HttpUnauthorizedException::class);
        $view($this->createRequest('GET', '/api/v1/me/store-backup'), $response, []);
    }

    public function testRestoreActionRejectsMissingAuthContext(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $restore = $container->get(\App\Application\Actions\Me\RestorePreMigrationBackupAction::class);
        $response = $app->getResponseFactory()->createResponse();
        $this->expectException(\Slim\Exception\HttpUnauthorizedException::class);
        $restore($this->createJsonRequest('POST', '/api/v1/me/store-backup/restore'), $response, []);
    }
}
