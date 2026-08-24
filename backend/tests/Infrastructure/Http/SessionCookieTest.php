<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Auth\AuthConfig;
use App\Infrastructure\Http\SessionCookie;
use Tests\TestCase;

class SessionCookieTest extends TestCase
{
    public function testSessionCookieReadAndFlags(): void
    {
        $cookie = new SessionCookie(false);
        $this->assertFalse($cookie->isSecure());
        $request = $this->createRequest(
            'GET',
            '/',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => 'abc']
        );
        $this->assertSame('abc', $cookie->read($request));
        $this->assertNull($cookie->read($this->createRequest('GET', '/')));

        $response = $this->getAppInstance()->getResponseFactory()->createResponse();
        $applied = $cookie->apply($response, str_repeat('a', 64), 99);
        $header = $applied->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Path=/', $header);
        $this->assertStringContainsString('Max-Age=99', $header);
        $this->assertDoesNotMatchRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $header);

        $secure = new SessionCookie(true);
        $this->assertTrue($secure->isSecure());
        $secureHeader = $secure->apply($response, 'x', 1)->getHeaderLine('Set-Cookie');
        $this->assertMatchesRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $secureHeader);

        $expired = $cookie->expire($response)->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('Max-Age=0', $expired);
        $this->assertStringContainsString('Expires=Thu, 01 Jan 1970 00:00:00 GMT', $expired);
    }
}
