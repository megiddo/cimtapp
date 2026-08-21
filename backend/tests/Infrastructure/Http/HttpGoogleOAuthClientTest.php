<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Http;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\GoogleUserInfo;
use App\Infrastructure\Http\FileGetContentsHttpTransport;
use App\Infrastructure\Http\HttpGoogleOAuthClient;
use App\Infrastructure\Http\HttpTransport;
use App\Infrastructure\Http\SessionCookie;
use Tests\TestCase;

class HttpGoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlIncludesPkceAndOmitsSecret(): void
    {
        $client = new HttpGoogleOAuthClient(
            'client-id',
            'client-secret',
            'http://localhost:8080/api/v1/auth/google/callback',
            new RecordingTransport(),
        );
        $this->assertTrue($client->isConfigured());
        $url = $client->authorizationUrl('state-1', 'challenge-1');
        $this->assertStringStartsWith(HttpGoogleOAuthClient::AUTH_URL, $url);
        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString('code_challenge=challenge-1', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringNotContainsString('client-secret', $url);
        $this->assertStringNotContainsString('client_secret', $url);
    }

    public function testUnconfiguredClient(): void
    {
        $client = new HttpGoogleOAuthClient('', '', '', new RecordingTransport());
        $this->assertFalse($client->isConfigured());
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('code', 'verifier');
    }

    public function testFetchUserHappyPathAndVerifiedFlags(): void
    {
        $http = new RecordingTransport();
        $http->responses = [
            json_encode(['access_token' => 'tok'], JSON_THROW_ON_ERROR),
            json_encode(['sub' => 'sub-1', 'email' => 'a@b.c', 'email_verified' => true], JSON_THROW_ON_ERROR),
        ];
        $client = $this->client($http);
        $user = $client->fetchUser('auth-code', 'verifier');
        $this->assertEquals(new GoogleUserInfo('sub-1', 'a@b.c', true), $user);
        $this->assertSame('POST', $http->calls[0]['method']);
        $this->assertSame(HttpGoogleOAuthClient::TOKEN_URL, $http->calls[0]['url']);
        $this->assertStringContainsString('code=auth-code', (string) $http->calls[0]['body']);
        $this->assertStringContainsString('code_verifier=verifier', (string) $http->calls[0]['body']);
        $this->assertStringContainsString('client_secret=secret', (string) $http->calls[0]['body']);
        $this->assertSame('GET', $http->calls[1]['method']);
        $this->assertSame('Bearer tok', $http->calls[1]['headers']['Authorization']);
    }

    public function testEmailVerifiedIntegerOne(): void
    {
        $http = new RecordingTransport();
        $http->responses = [
            '{"access_token":"tok"}',
            '{"sub":"s","email":"a@b.c","email_verified":"1"}',
        ];
        $this->assertTrue($this->client($http)->fetchUser('c', 'v')->emailVerified);
    }

    public function testRejectsMissingAccessToken(): void
    {
        $http = new RecordingTransport();
        $http->responses = ['{"error":"invalid_grant"}'];
        $this->expectException(GoogleOAuthException::class);
        $this->client($http)->fetchUser('c', 'v');
    }

    public function testRejectsInvalidJson(): void
    {
        $http = new RecordingTransport();
        $http->responses = ['not-json'];
        $this->expectException(GoogleOAuthException::class);
        $this->client($http)->fetchUser('c', 'v');
    }

    public function testRejectsJsonArray(): void
    {
        $http = new RecordingTransport();
        $http->responses = ['[]'];
        $this->expectException(GoogleOAuthException::class);
        $this->client($http)->fetchUser('c', 'v');
    }

    public function testRejectsIncompleteProfile(): void
    {
        $http = new RecordingTransport();
        $http->responses = ['{"access_token":"tok"}', '{"sub":"s"}'];
        $this->expectException(GoogleOAuthException::class);
        $this->client($http)->fetchUser('c', 'v');
    }

    public function testRejectsEmptyCode(): void
    {
        $this->expectException(GoogleOAuthException::class);
        $this->client(new RecordingTransport())->fetchUser('', 'v');
    }

    public function testUnverifiedNumericFalse(): void
    {
        $http = new RecordingTransport();
        $http->responses = [
            '{"access_token":"tok"}',
            '{"sub":"s","email":"a@b.c","email_verified":false}',
        ];
        $this->assertFalse($this->client($http)->fetchUser('c', 'v')->emailVerified);
    }

    public function testFileGetContentsTransportUsesFetcher(): void
    {
        $transport = new FileGetContentsHttpTransport(
            static function (string $url, bool $useInclude, $context): string {
                unset($useInclude);
                $opts = stream_context_get_options($context);
                if (($opts['http']['method'] ?? '') !== 'POST') {
                    throw new GoogleOAuthException('bad method');
                }

                return 'ok:' . $url;
            }
        );
        $this->assertSame('ok:https://example.test', $transport->request(
            'POST',
            'https://example.test',
            ['Accept' => 'application/json'],
            'a=b'
        ));
    }

    public function testFileGetContentsTransportThrowsOnFalse(): void
    {
        $transport = new FileGetContentsHttpTransport(static fn (): false => false);
        $this->expectException(GoogleOAuthException::class);
        $transport->request('GET', 'https://example.test');
    }

    public function testDefaultFetcherCanReadDataUri(): void
    {
        $transport = new FileGetContentsHttpTransport();
        $this->assertSame('hello', $transport->request('GET', 'data://text/plain,hello'));
    }

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

    private function client(HttpTransport $http): HttpGoogleOAuthClient
    {
        return new HttpGoogleOAuthClient(
            'id',
            'secret',
            'http://localhost:8080/callback',
            $http,
        );
    }
}

final class RecordingTransport implements HttpTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    /** @var list<string> */
    public array $responses = [];

    public function request(string $method, string $url, array $headers = [], ?string $body = null): string
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');
        if ($this->responses === []) {
            throw new GoogleOAuthException('Unable to reach Google.');
        }

        return array_shift($this->responses);
    }
}
