<?php

declare(strict_types=1);

namespace Tests\Application\Handlers;

use App\Application\Actions\ActionError;
use App\Application\Handlers\HttpErrorHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Factory\AppFactory;
use Tests\TestCase;

class HttpErrorHandlerTest extends TestCase
{
    public function testUnknownApiRouteIsNotFoundJson(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/api/v1/missing');
        $response = $app->handle($request);

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(ActionError::RESOURCE_NOT_FOUND, $payload['error']['type']);
        $this->assertNotEmpty($payload['error']['description']);
    }

    #[DataProvider('httpExceptionProvider')]
    public function testHttpExceptionTypes(string $exceptionClass, int $status, string $type): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );

        $exception = new $exceptionClass($request, 'denied');
        if ($exception instanceof HttpMethodNotAllowedException) {
            $exception->setAllowedMethods(['GET']);
        }
        $response = $handler($request, $exception, true, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($type, $payload['error']['type']);
        $this->assertNotEmpty($payload['error']['description']);
    }

    /**
     * @return array<string, array{0: class-string, 1: int, 2: string}>
     */
    public static function httpExceptionProvider(): array
    {
        return [
            'not found' => [HttpNotFoundException::class, 404, ActionError::RESOURCE_NOT_FOUND],
            'unauthorized' => [HttpUnauthorizedException::class, 401, ActionError::UNAUTHENTICATED],
            'forbidden' => [HttpForbiddenException::class, 403, ActionError::INSUFFICIENT_PRIVILEGES],
            'bad request' => [HttpBadRequestException::class, 400, ActionError::BAD_REQUEST],
            'not implemented' => [HttpNotImplementedException::class, 501, ActionError::NOT_IMPLEMENTED],
            'not allowed' => [HttpMethodNotAllowedException::class, 405, ActionError::NOT_ALLOWED],
        ];
    }

    public function testGenericExceptionHidesDetailsWhenNotDisplayed(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );

        $response = $handler($request, new RuntimeException('secret'), false, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(ActionError::SERVER_ERROR, $payload['error']['type']);
        $this->assertSame(
            'An internal error has occurred while processing your request.',
            $payload['error']['description']
        );
    }

    public function testGenericExceptionExposesMessageWhenDisplayed(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );

        $response = $handler($request, new RuntimeException('secret'), true, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('secret', $payload['error']['description']);
    }

    public function testGenericHttpExceptionKeepsServerErrorType(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );

        $exception = new HttpInternalServerErrorException($request, 'oops');
        $response = $handler($request, $exception, true, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(ActionError::SERVER_ERROR, $payload['error']['type']);
        $this->assertSame('oops', $payload['error']['description']);
    }

    public function testValidationExceptionIs422WithFields(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $exception = new \App\Domain\Auth\ValidationException(['email' => ['taken']]);
        $response = $handler($request, $exception, true, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(ActionError::VALIDATION_ERROR, $payload['error']['type']);
        $this->assertSame(['email' => ['taken']], $payload['error']['fields']);
    }

    public function testAuthenticationExceptionIs401(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $response = $handler(
            $request,
            new \App\Domain\Auth\AuthenticationException('Invalid email or password'),
            true,
            false,
            false
        );
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(ActionError::UNAUTHENTICATED, $payload['error']['type']);
        $this->assertSame('Invalid email or password', $payload['error']['description']);
    }

    public function testUserStoreLockedIs503(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $response = $handler(
            $request,
            new \App\Infrastructure\Persistence\UserStoreLockedException('locked-secret'),
            true,
            false,
            false
        );
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(ActionError::SERVICE_UNAVAILABLE, $payload['error']['type']);
        $this->assertSame(\App\Domain\Auth\AuthConfig::STORE_BUSY, $payload['error']['description']);
        $this->assertStringNotContainsString('locked-secret', (string) $response->getBody());
    }

    public function testCryptoExceptionHidesDetailsEvenWhenDisplayed(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $response = $handler(
            $request,
            new \App\Domain\Crypto\CryptoException('nonce=abc dek=fff'),
            true,
            false,
            false
        );
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(ActionError::SERVER_ERROR, $payload['error']['type']);
        $this->assertSame(
            'An internal error has occurred while processing your request.',
            $payload['error']['description']
        );
        $this->assertStringNotContainsString('dek=', (string) $response->getBody());
        $this->assertStringNotContainsString('nonce=', (string) $response->getBody());
    }

    public function testHttp503MapsToServiceUnavailableType(): void
    {
        $app = AppFactory::create();
        $request = $this->createRequest('GET', '/x');
        $handler = new HttpErrorHandler(
            $app->getCallableResolver(),
            $app->getResponseFactory(),
            new NullLogger()
        );
        $exception = new \Slim\Exception\HttpException($request, 'busy', 503);
        $response = $handler($request, $exception, true, false, false);
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(ActionError::SERVICE_UNAVAILABLE, $payload['error']['type']);
    }
}
