<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Auth;

use App\Domain\Auth\AuthConfig;
use Tests\TestCase;

class RateLimitHttpTest extends TestCase
{
    public function testLoginTripsThenRecoversWithGeneric429(): void
    {
        $app = $this->getAppInstance();
        $ip = ['REMOTE_ADDR' => '198.51.100.20'];
        $body = ['email' => 'limit@example.com', 'password' => 'wrong-password'];

        $lastOk = null;
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $lastOk = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', $body, [], '', $ip));
            $this->assertSame(401, $lastOk->getStatusCode());
            $this->assertSame(AuthConfig::GENERIC_LOGIN_ERROR, $this->json($lastOk)['error']['description']);
        }

        $blocked = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', $body, [], '', $ip));
        $this->assertSame(429, $blocked->getStatusCode());
        $payload = $this->json($blocked);
        $this->assertSame('TOO_MANY_REQUESTS', $payload['error']['type']);
        $this->assertSame(AuthConfig::RATE_LIMIT_MESSAGE, $payload['error']['description']);
        $this->assertStringNotContainsString('limit@example.com', (string) $blocked->getBody());
        $this->assertStringNotContainsString('198.51.100.20', (string) $blocked->getBody());
        $this->assertSame(
            $payload['error']['description'],
            $this->json($app->handle($this->createJsonRequest(
                'POST',
                '/api/v1/auth/login',
                ['email' => 'other@example.com', 'password' => 'wrong-password'],
                [],
                '',
                $ip,
            )))['error']['description']
        );

        $this->clock->advance(AuthConfig::RATE_LIMIT_WINDOW_SECONDS);
        $recovered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', $body, [], '', $ip));
        $this->assertSame(401, $recovered->getStatusCode());
        $this->assertSame(AuthConfig::GENERIC_LOGIN_ERROR, $this->json($recovered)['error']['description']);
    }

    public function testGoogleStartTripsThenRecovers(): void
    {
        $app = $this->getAppInstance();
        $server = ['REMOTE_ADDR' => '198.51.100.21'];

        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $ok = $app->handle($this->createRequest(
                'GET',
                '/api/v1/auth/google/start',
                ['HTTP_ACCEPT' => 'application/json'],
                [],
                $server,
            ));
            $this->assertSame(302, $ok->getStatusCode());
        }

        $blocked = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/start',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            $server,
        ));
        $this->assertSame(429, $blocked->getStatusCode());
        $this->assertSame(AuthConfig::RATE_LIMIT_MESSAGE, $this->json($blocked)['error']['description']);
        $this->assertSame('TOO_MANY_REQUESTS', $this->json($blocked)['error']['type']);

        $this->clock->advance(AuthConfig::RATE_LIMIT_WINDOW_SECONDS);
        $recovered = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/start',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            $server,
        ));
        $this->assertSame(302, $recovered->getStatusCode());
    }
}
