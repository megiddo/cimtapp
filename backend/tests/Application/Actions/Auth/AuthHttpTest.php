<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Auth;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\GoogleUserInfo;
use App\Domain\Crypto\Crypto;
use App\Infrastructure\Persistence\UserStore;
use PDO;
use Slim\App;
use Tests\TestCase;

class AuthHttpTest extends TestCase
{
    public function testRegisterNormalizesEmailSetsCookieAndSeedsStore(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => '  Foo@Example.COM  ',
            'password' => 'twelvechars!!',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame(201, $payload['statusCode']);
        $this->assertMePayload($payload['data'], 'foo@example.com', true, false);
        $this->assertSessionCookie($response, false);

        $this->assertUserStoreSeeded($app, 'foo@example.com', true, false);

        $global = $this->globalPdo($app);
        $hash = (string) $global->query("SELECT password_hash FROM users WHERE email = 'foo@example.com'")->fetchColumn();
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertNotFalse(
            $global->query("SELECT last_login_at FROM users WHERE email = 'foo@example.com'")->fetchColumn()
        );
    }

    public function testRegisterDuplicateEmailIsGeneric422(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'dup@example.com',
            'password' => 'twelvechars!!',
        ]));
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'DUP@example.com',
            'password' => 'twelvechars!!',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['type'] ?? null);
        $this->assertSame(['email' => [AuthConfig::EMAIL_TAKEN]], $payload['error']['fields']);
        $this->assertStringNotContainsString('Google', (string) $response->getBody());
        $this->assertStringNotContainsString('password account', (string) $response->getBody());
    }

    public function testRegisterValidationFieldMap(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame('VALIDATION_ERROR', $payload['error']['type']);
        $this->assertArrayHasKey('email', $payload['error']['fields']);
        $this->assertArrayHasKey('password', $payload['error']['fields']);
    }

    public function testRegisterAcceptsExactlyTwelveCharPassword(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'len12@example.com',
            'password' => '123456789012',
        ]));
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testLoginSuccessAndGenericFailure(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'login@example.com',
            'password' => 'twelvechars!!',
        ]));

        $ok = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', [
            'email' => '  LOGIN@example.com ',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertMePayload($this->json($ok)['data'], 'login@example.com', true, false);
        $this->assertSessionCookie($ok, false);

        $badPassword = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]));
        $this->assertSame(401, $badPassword->getStatusCode());
        $this->assertSame(AuthConfig::GENERIC_LOGIN_ERROR, $this->json($badPassword)['error']['description']);

        $unknown = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(401, $unknown->getStatusCode());
        $this->assertSame(AuthConfig::GENERIC_LOGIN_ERROR, $this->json($unknown)['error']['description']);
        $this->assertSame(
            $this->json($badPassword)['error']['description'],
            $this->json($unknown)['error']['description']
        );
    }

    public function testLogoutClearsSessionAndIsIdempotent(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'out@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);

        $logout = $app->handle($this->createJsonRequest(
            'POST',
            '/api/v1/auth/logout',
            [],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $logout->getStatusCode());
        $this->assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));

        $me = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(401, $me->getStatusCode());

        $again = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/logout', []));
        $this->assertSame(200, $again->getStatusCode());
    }

    public function testMeRequiresAuthAndOmitsDek(): void
    {
        $app = $this->getAppInstance();
        $unauth = $app->handle($this->createRequest('GET', '/api/v1/me'));
        $this->assertSame(401, $unauth->getStatusCode());
        $this->assertSame('UNAUTHENTICATED', $this->json($unauth)['error']['type']);

        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'me@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);
        $me = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $me->getStatusCode());
        $body = (string) $me->getBody();
        $this->assertMePayload($this->json($me)['data'], 'me@example.com', true, false);
        $this->assertStringNotContainsString('encrypted_dek', $body);
        $this->assertStringNotContainsString('dek_nonce', $body);
    }

    public function testExpiredSessionIsRejected(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'exp@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);
        $this->globalPdo($app)->exec("UPDATE sessions SET expires_at = '2000-01-01T00:00:00Z'");

        $me = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(401, $me->getStatusCode());
    }

    public function testSlidingSessionExtendsExpiry(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'slide@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);
        $before = (string) $this->globalPdo($app)
            ->query('SELECT expires_at FROM sessions WHERE id = ' . $this->globalPdo($app)->quote($sid))
            ->fetchColumn();

        $this->clock->advance(86400);
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $after = (string) $this->globalPdo($app)
            ->query('SELECT expires_at FROM sessions WHERE id = ' . $this->globalPdo($app)->quote($sid))
            ->fetchColumn();
        $this->assertNotSame($before, $after);
        $this->assertTrue($after > $before);
    }

    public function testSetPasswordOnGoogleFirstAccount(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('sub-setpw', 'gfirst@example.com', true);
        $start = $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $this->assertSame(302, $start->getStatusCode());
        $state = $this->google->lastState;
        $callback = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $state,
        ));
        $this->assertSame(302, $callback->getStatusCode());
        $this->assertSame('http://localhost:8080/', $callback->getHeaderLine('Location'));
        $sid = $this->sessionIdFrom($callback);

        $set = $app->handle($this->createJsonRequest(
            'POST',
            '/api/v1/me/password',
            ['password' => 'twelvechars!!'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(200, $set->getStatusCode());
        $this->assertMePayload($this->json($set)['data'], 'gfirst@example.com', true, true);
        $this->assertUserStoreSeeded($app, 'gfirst@example.com', true, true);

        $login = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'gfirst@example.com',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(200, $login->getStatusCode());
    }

    public function testGoogleLinksToExistingPasswordAccount(): void
    {
        $app = $this->getAppInstance();
        $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'link@example.com',
            'password' => 'twelvechars!!',
        ]));

        $this->google->user = new GoogleUserInfo('sub-link', 'LINK@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $callback = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $this->assertSame(302, $callback->getStatusCode());
        $this->assertSame('http://localhost:8080/', $callback->getHeaderLine('Location'));

        $count = (int) $this->globalPdo($app)->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertSame(1, $count);
        $row = $this->globalPdo($app)->query("SELECT google_sub FROM users WHERE email = 'link@example.com'")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('sub-link', $row['google_sub']);
        $this->assertUserStoreSeeded($app, 'link@example.com', true, true);
    }

    public function testGoogleUnverifiedEmailIsRejected(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('sub-unverified', 'nope@example.com', false);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $callback = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $this->assertSame(302, $callback->getStatusCode());
        $this->assertStringContainsString('/login?error=google', $callback->getHeaderLine('Location'));
        $this->assertSame(0, (int) $this->globalPdo($app)->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $this->assertSame('', $callback->getHeaderLine('Set-Cookie'));
    }

    public function testGoogleSubConflictFailsSafely(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('shared-sub', 'a@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));

        $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'b@example.com',
            'password' => 'twelvechars!!',
        ]));

        $this->google->user = new GoogleUserInfo('shared-sub', 'b@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $callback = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));
        $this->assertSame(302, $callback->getStatusCode());
        $this->assertStringContainsString('/login?error=google', $callback->getHeaderLine('Location'));
        $b = $this->globalPdo($app)->query("SELECT google_sub FROM users WHERE email = 'b@example.com'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNull($b['google_sub']);
    }

    public function testGoogleStartRequiresConfiguration(): void
    {
        $app = $this->getAppInstance();
        $this->google->configured = false;
        $response = $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('SERVICE_UNAVAILABLE', $this->json($response)['error']['type']);
    }

    public function testGoogleCallbackRejectsInvalidState(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('sub', 'x@example.com', true);
        $response = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=deadbeef',
        ));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/login?error=google', $response->getHeaderLine('Location'));
    }

    public function testSecureCookieFlagWhenConfigured(): void
    {
        $this->setEnv('SESSION_SECURE', 'true');
        $app = $this->getAppInstance();
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'secure@example.com',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSessionCookie($response, true);
    }

    public function testInvalidSessionIdIs401(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest(
            'GET',
            '/api/v1/me',
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => 'not-a-valid-session-id'],
        ));
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGoogleOnlyAccountCannotPasswordLogin(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('sub-only', 'only@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));

        $login = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/login', [
            'email' => 'only@example.com',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(401, $login->getStatusCode());
        $this->assertSame(AuthConfig::GENERIC_LOGIN_ERROR, $this->json($login)['error']['description']);
    }

    public function testPasswordRegisterRejectedWhenGoogleAccountExists(): void
    {
        $app = $this->getAppInstance();
        $this->google->user = new GoogleUserInfo('sub-exists', 'taken@example.com', true);
        $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'code=ok&state=' . $this->google->lastState,
        ));

        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'taken@example.com',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['email' => [AuthConfig::EMAIL_TAKEN]], $this->json($response)['error']['fields']);
    }

    public function testGoogleStartRedirectsToAuthorizationEndpoint(): void
    {
        $app = $this->getAppInstance();
        $response = $app->handle($this->createRequest('GET', '/api/v1/auth/google/start'));
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('code_challenge_method=S256', $location);
        $this->assertStringContainsString('state=', $location);
        $this->assertNotSame('', $this->google->lastState);
        $this->assertNotSame('', $this->google->lastChallenge);
    }

    public function testGoogleCallbackDeniedAndMissingCode(): void
    {
        $app = $this->getAppInstance();
        $denied = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'error=access_denied',
        ));
        $this->assertSame(302, $denied->getStatusCode());
        $this->assertStringContainsString('/login?error=google', $denied->getHeaderLine('Location'));

        $missing = $app->handle($this->createRequest(
            'GET',
            '/api/v1/auth/google/callback',
            ['HTTP_ACCEPT' => 'application/json'],
            [],
            [],
            'state=abc',
        ));
        $this->assertStringContainsString('/login?error=google', $missing->getHeaderLine('Location'));
    }

    public function testSetPasswordTooShortIs422(): void
    {
        $app = $this->getAppInstance();
        $registered = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => 'pw@example.com',
            'password' => 'twelvechars!!',
        ]));
        $sid = $this->sessionIdFrom($registered);
        $response = $app->handle($this->createJsonRequest(
            'POST',
            '/api/v1/me/password',
            ['password' => 'short'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('password', $this->json($response)['error']['fields']);
    }

    private function assertSessionCookie(\Psr\Http\Message\ResponseInterface $response, bool $secure): string
    {
        $header = $response->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString(AuthConfig::SESSION_COOKIE . '=', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Path=/', $header);
        if ($secure) {
            $this->assertMatchesRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $header);
        } else {
            $this->assertDoesNotMatchRegularExpression('/(?:^|;\\s*)Secure(?:;|$)/', $header);
        }

        return $this->sessionIdFrom($response);
    }

    private function assertUserStoreSeeded(App $app, string $email, bool $hasPassword, bool $hasGoogle): void
    {
        $container = $app->getContainer();
        $this->assertNotNull($container);
        $row = $this->globalPdo($app)
            ->query('SELECT * FROM users WHERE email = ' . $this->globalPdo($app)->quote($email))
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        $dek = $container->get(Crypto::class)->unwrapDek((string) $row['dek_nonce'], (string) $row['encrypted_dek']);
        $container->get(UserStore::class)->withUnlocked(
            (string) $row['id'],
            $dek,
            function (PDO $pdo) use ($email, $hasPassword, $hasGoogle): void {
                $account = $pdo->query('SELECT * FROM account')->fetch(PDO::FETCH_ASSOC);
                $this->assertIsArray($account);
                $this->assertSame($email, $account['email']);
                $this->assertSame($hasPassword, is_string($account['password_hash']) && $account['password_hash'] !== '');
                $this->assertSame($hasGoogle, is_string($account['google_sub']) && $account['google_sub'] !== '');

                $syringe = $pdo->query('SELECT * FROM syringe_profiles')->fetch(PDO::FETCH_ASSOC);
                $this->assertIsArray($syringe);
                $this->assertSame(AuthConfig::DEFAULT_SYRINGE_LABEL, $syringe['label']);
                $this->assertEqualsWithDelta(0.5, (float) $syringe['volume_ml'], 0.0001);
                $this->assertEqualsWithDelta(50.0, (float) $syringe['capacity_iu'], 0.0001);
                $this->assertSame(1, (int) $syringe['is_default']);
                $this->assertSame(0, (int) $syringe['quantity']);
            }
        );
    }
}
