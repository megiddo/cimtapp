<?php

declare(strict_types=1);

namespace Tests\Domain\Auth;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\OauthState;
use App\Domain\Auth\OauthStateRepository;
use App\Domain\Auth\OauthStateService;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class OauthStateServiceTest extends TestCase
{
    public function testConsumeIsSingleUseAndRejectsExpired(): void
    {
        $clock = FrozenClock::at('2026-08-20T15:00:00Z');
        $repo = new InMemoryOauthStateRepository();
        $service = new OauthStateService($repo, new IdGenerator(), $clock);
        $issued = $service->issue('verifier-1');
        $this->assertSame('verifier-1', $issued->codeVerifier);

        $first = $service->consume($issued->state);
        $this->assertNotNull($first);
        $this->assertSame('verifier-1', $first->codeVerifier);
        $this->assertNull($service->consume($issued->state));

        $again = $service->issue('verifier-2');
        $clock->advance(11 * 60);
        $this->assertNull($service->consume($again->state));
        $this->assertNull($service->consume(''));
    }

    public function testEmptyVerifierIsRejected(): void
    {
        $clock = FrozenClock::at('2026-08-20T15:00:00Z');
        $repo = new InMemoryOauthStateRepository();
        $service = new OauthStateService($repo, new IdGenerator(), $clock);
        $repo->insert(new OauthState('abc', '2099-01-01T00:00:00Z', '/', ''));
        $this->assertNull($service->consume('abc'));
    }
}

final class InMemoryOauthStateRepository implements OauthStateRepository
{
    /** @var array<string, OauthState> */
    public array $rows = [];

    public function insert(OauthState $state): void
    {
        $this->rows[$state->state] = $state;
    }

    public function findByState(string $state): ?OauthState
    {
        return $this->rows[$state] ?? null;
    }

    public function delete(string $state): void
    {
        unset($this->rows[$state]);
    }
}
