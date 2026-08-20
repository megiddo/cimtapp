<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Boot\BootServices;
use App\Application\Handlers\HttpErrorHandler;
use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\Clock;
use App\Domain\Auth\GoogleOAuthClient;
use App\Infrastructure\Persistence\GlobalConnection;
use DI\ContainerBuilder;
use Exception;
use FilesystemIterator;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\NullLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request as SlimRequest;
use Slim\Psr7\Uri;
use SplFileInfo;
use Tests\Support\FakeGoogleOAuthClient;
use Tests\Support\FrozenClock;

class TestCase extends PHPUnit_TestCase
{
    private ?string $isolatedDataDir = null;

    protected FrozenClock $clock;

    protected FakeGoogleOAuthClient $google;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnv('SESSION_SECURE', 'false');
    }

    /**
     * @throws Exception
     */
    protected function getAppInstance(?callable $extraDefinitions = null): App
    {
        $this->isolateDataDir();
        if (!isset($this->clock)) {
            $this->clock = FrozenClock::at('2026-08-20T15:00:00Z');
        }
        if (!isset($this->google)) {
            $this->google = new FakeGoogleOAuthClient();
        }

        $containerBuilder = new ContainerBuilder();

        $settings = require __DIR__ . '/../app/settings.php';
        $settings($containerBuilder);

        $dependencies = require __DIR__ . '/../app/dependencies.php';
        $dependencies($containerBuilder);

        $repositories = require __DIR__ . '/../app/repositories.php';
        $repositories($containerBuilder);

        $containerBuilder->addDefinitions([
            Clock::class => fn (): Clock => $this->clock,
            GoogleOAuthClient::class => fn (): GoogleOAuthClient => $this->google,
        ]);
        if ($extraDefinitions !== null) {
            $extraDefinitions($containerBuilder);
        }

        $container = $containerBuilder->build();
        $container->get(BootServices::class)->boot();

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $middleware = require __DIR__ . '/../app/middleware.php';
        $middleware($app);

        $routes = require __DIR__ . '/../app/routes.php';
        $routes($app);

        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $errorHandler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $errorMiddleware = $app->addErrorMiddleware(true, false, false);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        return $app;
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $serverParams
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = [],
        string $query = '',
    ): Request {
        $uri = new Uri('', '', 80, $path, $query);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $cookies
     */
    protected function createJsonRequest(
        string $method,
        string $path,
        array $body = [],
        array $cookies = [],
        string $query = '',
    ): Request {
        $uri = new Uri('', '', 80, $path, $query);
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, json_encode($body, JSON_THROW_ON_ERROR));
        rewind($handle);
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        $h->addHeader('HTTP_ACCEPT', 'application/json');
        $h->addHeader('Content-Type', 'application/json');

        return new SlimRequest($method, $uri, $h, $cookies, [], $stream);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }

    protected function sessionIdFrom(Response $response): string
    {
        $header = implode('; ', $response->getHeader('Set-Cookie'));
        $this->assertMatchesRegularExpression('/' . AuthConfig::SESSION_COOKIE . '=([0-9a-f]{64})/', $header);
        preg_match('/' . AuthConfig::SESSION_COOKIE . '=([0-9a-f]{64})/', $header, $matches);

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function assertMePayload(array $data, string $email, bool $hasPassword, bool $hasGoogle): void
    {
        $this->assertSame($email, $data['email']);
        $this->assertSame($hasPassword, $data['has_password']);
        $this->assertSame($hasGoogle, $data['has_google']);
        $this->assertArrayHasKey('remainder', $data);
        $this->assertNull($data['remainder']);
        $this->assertArrayNotHasKey('encrypted_dek', $data);
        $this->assertArrayNotHasKey('dek_nonce', $data);
        $this->assertArrayNotHasKey('dek', $data);
        $this->assertArrayNotHasKey('password_hash', $data);
        $this->assertArrayNotHasKey('google_sub', $data);
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('encrypted_dek', $encoded);
        $this->assertStringNotContainsString('dek_nonce', $encoded);
    }

    protected function globalPdo(App $app): \PDO
    {
        $container = $app->getContainer();
        $this->assertNotNull($container);

        return $container->get(GlobalConnection::class)->pdo();
    }

    protected function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    protected function isolateDataDir(): string
    {
        if ($this->isolatedDataDir === null) {
            $this->isolatedDataDir = $this->makeTempDir('cimtapp-http-');
            $this->setEnv('DATA_DIR', $this->isolatedDataDir);
        }

        return $this->isolatedDataDir;
    }

    protected function tearDown(): void
    {
        if ($this->isolatedDataDir !== null) {
            $this->removeDir($this->isolatedDataDir);
            $this->isolatedDataDir = null;
        }
        $this->setEnv('SESSION_SECURE', 'false');
        parent::tearDown();
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $serverParams
     */
    protected function makeTempDir(string $prefix = 'cimtapp-'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(8));
        mkdir($dir, 0700, true);

        return $dir;
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
