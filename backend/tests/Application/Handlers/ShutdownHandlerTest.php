<?php

declare(strict_types=1);

namespace Tests\Application\Handlers;

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;
use Tests\TestCase;

class TestableShutdownHandler extends ShutdownHandler
{
    /** @var array{type: int, message: string, file: string, line: int}|null */
    public ?array $forcedError = null;

    protected function lastError(): ?array
    {
        return $this->forcedError;
    }
}

class ShutdownHandlerTest extends TestCase
{
    public function testInvokeReturnsWhenNoLastError(): void
    {
        $handler = $this->makeHandler(true);
        $handler();
        $this->addToAssertionCount(1);
    }

    public function testInvokeEmitsJsonWhenLastErrorPresent(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/');
        $errorHandler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $handler = new TestableShutdownHandler($request, $errorHandler, true);
        $handler->forcedError = [
            'type' => E_ERROR,
            'message' => 'boom',
            'file' => '/tmp/x.php',
            'line' => 4,
        ];

        ob_start();
        $handler();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('FATAL ERROR: boom', $output);
        $this->assertStringContainsString('SERVER_ERROR', $output);
    }

    public function testHiddenDetailsMessage(): void
    {
        $handler = $this->makeHandler(false);
        $message = $handler->getErrorMessage([
            'type' => E_ERROR,
            'message' => 'boom',
            'file' => '/tmp/x.php',
            'line' => 9,
        ]);

        $this->assertSame(
            'An error while processing your request. Please try again later.',
            $message
        );
    }

    public function testUserErrorMessage(): void
    {
        $handler = $this->makeHandler(true);
        $this->assertSame(
            'FATAL ERROR: boom. on line 9 in file /tmp/x.php.',
            $handler->getErrorMessage([
                'type' => E_USER_ERROR,
                'message' => 'boom',
                'file' => '/tmp/x.php',
                'line' => 9,
            ])
        );
    }

    public function testUserWarningMessage(): void
    {
        $handler = $this->makeHandler(true);
        $this->assertSame(
            'WARNING: careful',
            $handler->getErrorMessage([
                'type' => E_USER_WARNING,
                'message' => 'careful',
                'file' => '/tmp/w.php',
                'line' => 1,
            ])
        );
    }

    public function testUserNoticeMessage(): void
    {
        $handler = $this->makeHandler(true);
        $this->assertSame(
            'NOTICE: heads up',
            $handler->getErrorMessage([
                'type' => E_USER_NOTICE,
                'message' => 'heads up',
                'file' => '/tmp/n.php',
                'line' => 2,
            ])
        );
    }

    public function testDefaultFatalMessage(): void
    {
        $handler = $this->makeHandler(true);
        $this->assertSame(
            'FATAL ERROR: segfault. on line 3 in file /tmp/f.php.',
            $handler->getErrorMessage([
                'type' => E_ERROR,
                'message' => 'segfault',
                'file' => '/tmp/f.php',
                'line' => 3,
            ])
        );
    }

    private function makeHandler(bool $display): ShutdownHandler
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/');
        $errorHandler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );

        return new ShutdownHandler($request, $errorHandler, $display);
    }
}
