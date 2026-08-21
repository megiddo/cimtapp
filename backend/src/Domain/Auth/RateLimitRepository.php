<?php

declare(strict_types=1);

namespace App\Domain\Auth;

interface RateLimitRepository
{
    public function purgeBefore(string $cutoffInclusive): void;

    public function count(string $bucket): int;

    public function hit(string $bucket, string $at): void;
}
