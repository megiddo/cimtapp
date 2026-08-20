<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Auth\AuthConfig;
use App\Infrastructure\Http\ClientIp;
use App\Infrastructure\Http\SessionCookie;
use Tests\TestCase;

class SessionCookieAndClientIpTest extends TestCase
{
    public function testApplyIncludesRequiredFlagsAndSecureWhenEnabled(): void
    {
        $factory = $this->getAppInstance()->getResponseFactory();
        $insecure = (new SessionCookie(false))->apply($factory->createResponse(), str_repeat('a', 64), 99);
        $header = $insecure->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString(AuthConfig::SESSION_COOKIE . '=', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Path=/', $header);
        $this->assertDoesNotMatchRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $header);

        $secure = (new SessionCookie(true))->apply($factory->createResponse(), str_repeat('b', 64), 99);
        $this->assertMatchesRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $secure->getHeaderLine('Set-Cookie'));
        $this->assertTrue((new SessionCookie(true))->isSecure());
        $this->assertFalse((new SessionCookie(false))->isSecure());
    }

    public function testExpireClearsCookieAndKeepsSecureWhenConfigured(): void
    {
        $factory = $this->getAppInstance()->getResponseFactory();
        $expired = (new SessionCookie(true))->expire($factory->createResponse());
        $header = $expired->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('Max-Age=0', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertMatchesRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $header);
    }

    public function testClientIpReadsRemoteAddrAndFallsBack(): void
    {
        $withIp = $this->createRequest(
            'GET',
            '/x',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            ['REMOTE_ADDR' => '203.0.113.9'],
        );
        $this->assertSame('203.0.113.9', ClientIp::from($withIp));
        $this->assertSame('unknown', ClientIp::from($this->createRequest('GET', '/x')));
        $empty = $this->createRequest('GET', '/x', ['HTTP_ACCEPT' => 'application/json'], [], ['REMOTE_ADDR' => '']);
        $this->assertSame('unknown', ClientIp::from($empty));
    }
}
