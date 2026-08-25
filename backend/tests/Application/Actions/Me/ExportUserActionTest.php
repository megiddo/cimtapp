<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Me;

use App\Domain\Auth\AuthConfig;
use PDO;
use Tests\TestCase;

class ExportUserActionTest extends TestCase
{
    public function testExportDownloadsSqliteAndLeavesNoPlaintext(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'export@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);

        $response = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me/export',
            ['HTTP_ACCEPT' => 'application/octet-stream'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/octet-stream', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('peptrack-export.sqlite', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));

        $bytes = (string) $response->getBody();
        $this->assertStringStartsWith('SQLite format 3', $bytes);

        $tmp = $this->makeTempDir('cimtapp-export-read-');
        $path = $tmp . '/export.sqlite';
        file_put_contents($path, $bytes);
        $pdo = new PDO('sqlite:' . $path);
        $email = (string) $pdo->query('SELECT email FROM account')->fetchColumn();
        $this->assertSame('export@example.com', $email);
        $this->assertNotFalse($pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'user_store_format'")->fetchColumn());
        $this->assertSame(4, (int) $pdo->query('SELECT version FROM user_store_format WHERE id = 1')->fetchColumn());
        $this->removeDir($tmp);

        $dataDir = $this->isolateDataDir();
        $this->assertSame([], glob($dataDir . '/tmp/*.sqlite') ?: []);

        $me = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $me->getStatusCode());
    }

    public function testExportRequiresAuth(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/api/v1/me/export'));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testExportActionRejectsMissingAuthContext(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $action = $container->get(\App\Application\Actions\Me\ExportUserAction::class);
        $request = $this->createRequest('GET', '/api/v1/me/export');
        $response = $app->getResponseFactory()->createResponse();
        $this->expectException(\Slim\Exception\HttpUnauthorizedException::class);
        $action($request, $response, []);
    }
}
