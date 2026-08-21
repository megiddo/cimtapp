<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\EmailNormalizer;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Session;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\SystemClock;
use App\Domain\Auth\User;
use App\Domain\Auth\ValidationException;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class AuthDomainTest extends TestCase
{
    public function testEmailNormalizeLowercasesAndTrims(): void
    {
        $n = new EmailNormalizer();
        $this->assertSame('foo@example.com', $n->normalize('  Foo@Example.COM  '));
        $this->assertTrue($n->isValid('foo@example.com'));
        $this->assertFalse($n->isValid(''));
        $this->assertFalse($n->isValid('not-an-email'));
        $this->assertFalse($n->isValid("foo\0@example.com"));
    }

    public function testPasswordHasherArgon2idRoundTrip(): void
    {
        $hasher = new PasswordHasher(false);
        $hash = $hasher->hash('twelvechars!!');
        $this->assertTrue($hasher->isArgon2id($hash));
        $this->assertTrue($hasher->verify('twelvechars!!', $hash));
        $this->assertFalse($hasher->verify('wrong-password', $hash));
        $this->assertFalse($hasher->verify('twelvechars!!', $hasher->dummyHash()));
        $this->assertTrue($hasher->isArgon2id($hasher->dummyHash()));
    }

    public function testInteractiveHasherStillArgon2id(): void
    {
        $hasher = new PasswordHasher(true);
        $hash = $hasher->hash('twelvechars!!');
        $this->assertTrue($hasher->isArgon2id($hash));
        $this->assertTrue($hasher->verify('twelvechars!!', $hash));
    }

    public function testIdGeneratorShapes(): void
    {
        $ids = new IdGenerator();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $ids->uuid()
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $ids->sessionId());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $ids->oauthState());
        $verifier = $ids->pkceVerifier();
        $this->assertGreaterThanOrEqual(43, strlen($verifier));
        $challenge = $ids->pkceChallenge($verifier);
        $this->assertNotSame($verifier, $challenge);
        $this->assertSame($challenge, $ids->pkceChallenge($verifier));
    }

    public function testRemainingIuIsOptionalOnValidationException(): void
    {
        $plain = new ValidationException(['email' => ['taken']]);
        $this->assertNull($plain->remainingIu());
        $over = new ValidationException(['iu' => ['nope']], 'nope', 18.5);
        $this->assertSame(18.5, $over->remainingIu());
        $this->assertSame('nope', $over->getMessage());
    }

    public function testUserMeArrayOmitsSecrets(): void
    {
        $user = new User('id', 'a@b.c', 'hash', 'sub', 'dek-cipher', 'nonce', 'now', null);
        $me = $user->toMeArray();
        $this->assertTrue($user->hasPassword());
        $this->assertTrue($user->hasGoogle());
        $this->assertSame([
            'email' => 'a@b.c',
            'has_password' => true,
            'has_google' => true,
            'remainder' => null,
        ], $me);
        $this->assertArrayNotHasKey('encrypted_dek', $me);

        $empty = new User('id', 'a@b.c', '', '', 'c', 'n', 'now', null);
        $this->assertFalse($empty->hasPassword());
        $this->assertFalse($empty->hasGoogle());
        $nulls = new User('id', 'a@b.c', null, null, 'c', 'n', 'now', null);
        $this->assertFalse($nulls->hasPassword());
        $this->assertFalse($nulls->hasGoogle());
    }

    public function testCredentialParserAcceptsArrayAndObject(): void
    {
        $parser = new CredentialParser();
        $this->assertSame(['email' => 'a', 'password' => 'b'], $parser->parse(['email' => 'a', 'password' => 'b']));
        $this->assertSame(['email' => 'a', 'password' => 'b'], $parser->parse((object) ['email' => 'a', 'password' => 'b']));
        $this->assertSame(['email' => '', 'password' => ''], $parser->parse(null));
        $this->assertSame(['email' => '', 'password' => ''], $parser->parse(['email' => 1, 'password' => []]));
    }

    public function testSessionExpiryBoundaryAndDestroy(): void
    {
        $clock = FrozenClock::at('2026-08-20T15:00:00Z');
        $repo = new InMemorySessionRepository();
        $service = new SessionService($repo, new IdGenerator(), $clock);
        $session = $service->create('user-id');
        $this->assertFalse($service->isExpired($session));
        $this->assertSame(AuthConfig::SESSION_TTL_SECONDS, $service->ttlSeconds());

        $clock->advance(AuthConfig::SESSION_TTL_SECONDS);
        $this->assertTrue($service->isExpired($session));
        $this->assertNull($service->loadValid($session->id));

        $service->destroy('');
        $this->assertNull($service->loadValid('abc'));
        $this->assertNull($service->loadValid(str_repeat('0', 64)));
    }

    public function testSessionTouchAndWithExpiresAt(): void
    {
        $clock = FrozenClock::at('2026-08-20T15:00:00Z');
        $repo = new InMemorySessionRepository();
        $service = new SessionService($repo, new IdGenerator(), $clock);
        $session = $service->create('user-id');
        $clock->advance(60);
        $touched = $service->touch($session);
        $this->assertNotSame($session->expiresAt, $touched->expiresAt);
        $this->assertSame($session->id, $touched->id);
        $copy = $session->withExpiresAt('2099-01-01T00:00:00Z');
        $this->assertSame('2099-01-01T00:00:00Z', $copy->expiresAt);
    }

    public function testSystemClockIsUtc(): void
    {
        $now = (new SystemClock())->now();
        $this->assertSame('UTC', $now->getTimezone()->getName());
    }

    public function testValidationExceptionExposesFields(): void
    {
        $e = new ValidationException(['email' => ['bad']], 'Validation failed.');
        $this->assertSame(['email' => ['bad']], $e->fields());
        $this->assertSame('Validation failed.', $e->getMessage());
    }
}

final class InMemorySessionRepository implements \App\Domain\Auth\SessionRepository
{
    /** @var array<string, Session> */
    public array $rows = [];

    public function insert(Session $session): void
    {
        $this->rows[$session->id] = $session;
    }

    public function findById(string $id): ?Session
    {
        return $this->rows[$id] ?? null;
    }

    public function delete(string $id): void
    {
        unset($this->rows[$id]);
    }

    public function updateExpiry(string $id, string $expiresAt): void
    {
        if (isset($this->rows[$id])) {
            $this->rows[$id] = $this->rows[$id]->withExpiresAt($expiresAt);
        }
    }
}
