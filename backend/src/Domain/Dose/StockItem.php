<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;

interface StockItem
{
    public function id(): string;

    public function kind(): string;

    public function remaining(): float;

    public function isArchived(): bool;

    public function archive(Clock $clock): void;
}
