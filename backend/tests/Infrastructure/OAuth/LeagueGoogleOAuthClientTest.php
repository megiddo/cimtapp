<?php

declare(strict_types=1);

namespace Tests\Infrastructure\OAuth;

use App\Domain\Auth\GoogleOAuthException;
use App\Domain\Auth\GoogleUserInfo;
use App\Infrastructure\OAuth\LeagueGoogleOAuthClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class LeagueGoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlIncludesPkceAndOmitsSecret(): void
    {
        $client = LeagueGoogleOAuthClient::fromCredentials(
            'client-id',
            'client-secret',
            'http://localhost:24780/api/v1/auth/google/callback',
        );
        $this->assertTrue($client->isConfigured());
        $url = $client->authorizationUrl('state-1', 'challenge-1');
        $this->assertStringStartsWith(LeagueGoogleOAuthClient::AUTH_URL, $url);
        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString('code_challenge=challenge-1', $url);
        $this->assertStringContainsString('code_challenge_method=S256', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('state=state-1', $url);
        $this->assertStringNotContainsString('client-secret', $url);
        $this->assertStringNotContainsString('client_secret', $url);
    }

    public function testUnconfiguredClient(): void
    {
        $client = LeagueGoogleOAuthClient::fromCredentials('', '', '');
        $this->assertFalse($client->isConfigured());
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('code', 'verifier');
    }

    public function testFetchUserHappyPathAndVerifiedFlags(): void
    {
        [$client, $history] = $this->client([
            $this->jsonResponse(200, ['access_token' => 'tok', 'token_type' => 'Bearer']),
            $this->jsonResponse(200, ['sub' => 'sub-1', 'email' => 'a@b.c', 'email_verified' => true]),
        ]);
        $user = $client->fetchUser('auth-code', 'verifier');
        $this->assertEquals(new GoogleUserInfo('sub-1', 'a@b.c', true), $user);
        $this->assertCount(2, $history);
        $tokenRequest = $history[0]['request'];
        $this->assertSame('POST', $tokenRequest->getMethod());
        $this->assertSame(LeagueGoogleOAuthClient::TOKEN_URL, (string) $tokenRequest->getUri());
        $body = (string) $tokenRequest->getBody();
        $this->assertStringContainsString('code=auth-code', $body);
        $this->assertStringContainsString('code_verifier=verifier', $body);
        $this->assertStringContainsString('client_secret=secret', $body);
        $userinfo = $history[1]['request'];
        $this->assertSame('GET', $userinfo->getMethod());
        $this->assertSame('Bearer tok', $userinfo->getHeaderLine('Authorization'));
    }

    public function testEmailVerifiedIntegerOne(): void
    {
        [$client] = $this->client([
            $this->jsonResponse(200, ['access_token' => 'tok', 'token_type' => 'Bearer']),
            $this->jsonResponse(200, ['sub' => 's', 'email' => 'a@b.c', 'email_verified' => '1']),
        ]);
        $this->assertTrue($client->fetchUser('c', 'v')->emailVerified);
    }

    public function testRejectsMissingAccessToken(): void
    {
        [$client] = $this->client([
            $this->jsonResponse(400, ['error' => 'invalid_grant']),
        ]);
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('c', 'v');
    }

    public function testRejectsInvalidJson(): void
    {
        [$client] = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], 'not-json'),
        ]);
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('c', 'v');
    }

    public function testRejectsJsonArray(): void
    {
        [$client] = $this->client([
            new Response(200, ['Content-Type' => 'application/json'], '[]'),
        ]);
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('c', 'v');
    }

    public function testRejectsIncompleteProfile(): void
    {
        [$client] = $this->client([
            $this->jsonResponse(200, ['access_token' => 'tok', 'token_type' => 'Bearer']),
            $this->jsonResponse(200, ['sub' => 's']),
        ]);
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('c', 'v');
    }

    public function testRejectsEmptyCode(): void
    {
        [$client] = $this->client([]);
        $this->expectException(GoogleOAuthException::class);
        $client->fetchUser('', 'v');
    }

    public function testUnverifiedNumericFalse(): void
    {
        [$client] = $this->client([
            $this->jsonResponse(200, ['access_token' => 'tok', 'token_type' => 'Bearer']),
            $this->jsonResponse(200, ['sub' => 's', 'email' => 'a@b.c', 'email_verified' => false]),
        ]);
        $this->assertFalse($client->fetchUser('c', 'v')->emailVerified);
    }

    /**
     * @param list<Response> $responses
     * @return array{0: LeagueGoogleOAuthClient, 1: \ArrayObject<int, array<string, mixed>>}
     */
    private function client(array $responses): array
    {
        $history = new \ArrayObject();
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($history));

        return [
            LeagueGoogleOAuthClient::fromCredentials(
                'id',
                'secret',
                'http://localhost:24780/callback',
                new Client(['handler' => $stack]),
            ),
            $history,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $status, array $payload): Response
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
