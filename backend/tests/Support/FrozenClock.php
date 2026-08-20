<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Auth\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public static function at(string $utc): self
    {
        return new self(new DateTimeImmutable($utc, new DateTimeZone('UTC')));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify('+' . $seconds . ' seconds');
    }
}
