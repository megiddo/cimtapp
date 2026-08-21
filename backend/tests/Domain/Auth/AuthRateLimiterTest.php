<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\AuthConfig;
use App\Domain\Auth\AuthRateLimiter;
use App\Domain\Auth\EmailNormalizer;
use App\Domain\Auth\RateLimitException;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalConnection;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\SqliteRateLimitRepository;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class AuthRateLimiterTest extends TestCase
{
    private string $dir;
    private FrozenClock $frozen;
    private AuthRateLimiter $limiter;
    private SqliteRateLimitRepository $hits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-rl-');
        $paths = new DataPaths($this->dir);
        (new GlobalMigrator($paths, dirname(__DIR__, 3) . '/migrations/global'))->migrate();
        $this->hits = new SqliteRateLimitRepository(new GlobalConnection($paths));
        $this->frozen = FrozenClock::at('2026-08-20T15:00:00Z');
        $this->limiter = new AuthRateLimiter($this->hits, $this->frozen, new EmailNormalizer());
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testLoginAllowsBudgetThenTripsAndRecovers(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardLogin('10.0.0.1', 'user@example.com');
        }

        try {
            $this->limiter->guardLogin('10.0.0.1', 'user@example.com');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(AuthConfig::RATE_LIMIT_MESSAGE, $e->getMessage());
        }

        $this->frozen->advance(AuthConfig::RATE_LIMIT_WINDOW_SECONDS);
        $this->limiter->guardLogin('10.0.0.1', 'user@example.com');
        $this->assertSame(1, $this->hits->count('login:ip:10.0.0.1'));
    }

    public function testLoginEmailBucketIsSharedAcrossIps(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardLogin('10.0.0.1', '  Same@Example.COM ');
        }

        $this->expectException(RateLimitException::class);
        $this->limiter->guardLogin('10.0.0.2', 'same@example.com');
    }

    public function testLoginIpBucketIsSharedAcrossEmails(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardLogin('10.0.0.9', 'u' . $i . '@example.com');
        }

        $this->expectException(RateLimitException::class);
        $this->limiter->guardLogin('10.0.0.9', 'other@example.com');
    }

    public function testEmptyEmailOnlyCountsIp(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardLogin('10.0.0.3', '');
        }

        $this->assertSame(0, $this->hits->count('login:email:'));
        $this->expectException(RateLimitException::class);
        $this->limiter->guardLogin('10.0.0.3', '');
    }

    public function testGoogleStartBudgetThenRecovers(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardGoogleStart('192.0.2.10');
        }

        try {
            $this->limiter->guardGoogleStart('192.0.2.10');
            $this->fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            $this->assertSame(AuthConfig::RATE_LIMIT_MESSAGE, $e->getMessage());
        }

        $this->frozen->advance(AuthConfig::RATE_LIMIT_WINDOW_SECONDS - 1);
        try {
            $this->limiter->guardGoogleStart('192.0.2.10');
            $this->fail('Expected RateLimitException before window elapses');
        } catch (RateLimitException) {
        }

        $this->frozen->advance(1);
        $this->limiter->guardGoogleStart('192.0.2.10');
        $this->assertSame(1, $this->hits->count('google-start:ip:192.0.2.10'));
    }

    public function testGoogleStartDoesNotConsumeLoginBudget(): void
    {
        for ($i = 0; $i < AuthConfig::RATE_LIMIT_MAX; $i++) {
            $this->limiter->guardGoogleStart('10.0.0.4');
        }
        $this->limiter->guardLogin('10.0.0.4', 'fresh@example.com');
        $this->assertSame(1, $this->hits->count('login:ip:10.0.0.4'));
    }
}
