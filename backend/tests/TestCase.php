<?php

declare(strict_types=1);

namespace Tests;

use App\Application\Boot\BootServices;
use App\Application\Handlers\HttpErrorHandler;
use DI\ContainerBuilder;
use Exception;
use FilesystemIterator;
use PHPUnit\Framework\TestCase as PHPUnit_TestCase;
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

class TestCase extends PHPUnit_TestCase
{
    /**
     * @throws Exception
     */
    protected function getAppInstance(): App
    {
        $containerBuilder = new ContainerBuilder();

        $settings = require __DIR__ . '/../app/settings.php';
        $settings($containerBuilder);

        $dependencies = require __DIR__ . '/../app/dependencies.php';
        $dependencies($containerBuilder);

        $repositories = require __DIR__ . '/../app/repositories.php';
        $repositories($containerBuilder);

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
        array $serverParams = []
    ): Request {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }

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
