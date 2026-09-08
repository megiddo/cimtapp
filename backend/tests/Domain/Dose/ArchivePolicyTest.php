<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\ArchivePolicy;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\StockItem;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class ArchivePolicyTest extends TestCase
{
    public function testArchivesEmptyOpenItem(): void
    {
        $item = new FakeStockItem('s1', 'syringe', 0.0, false);
        $this->assertSame('s1', $item->id());
        $this->assertSame('syringe', $item->kind());
        $clock = FrozenClock::at('2026-08-27T16:00:00Z');
        (new ArchivePolicy(new DoseCalculator()))->apply($item, $clock);
        $this->assertTrue($item->archived);
        $this->assertSame($clock, $item->archivedWith);
    }

    public function testRejectsRemainingStock(): void
    {
        $item = new FakeStockItem('s1', 'syringe', 2.0, false);
        try {
            (new ArchivePolicy(new DoseCalculator()))->apply($item, FrozenClock::at('2026-08-27T16:00:00Z'));
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['id' => [DoseConfig::ARCHIVE_NOT_EMPTY]], $e->fields());
            $this->assertSame(DoseConfig::ARCHIVE_NOT_EMPTY, $e->getMessage());
        }
        $this->assertFalse($item->archived);
    }

    public function testRejectsAlreadyArchived(): void
    {
        $item = new FakeStockItem('s1', 'syringe', 0.0, true);
        try {
            (new ArchivePolicy(new DoseCalculator()))->apply($item, FrozenClock::at('2026-08-27T16:00:00Z'));
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['id' => [DoseConfig::ALREADY_ARCHIVED]], $e->fields());
            $this->assertSame(DoseConfig::ALREADY_ARCHIVED, $e->getMessage());
        }
        $this->assertFalse($item->archived);
    }
}

final class FakeStockItem implements StockItem
{
    public bool $archived = false;

    public ?Clock $archivedWith = null;

    public function __construct(
        private readonly string $id,
        private readonly string $kind,
        private readonly float $remaining,
        private readonly bool $isArchived,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function remaining(): float
    {
        return $this->remaining;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function archive(Clock $clock): void
    {
        $this->archived = true;
        $this->archivedWith = $clock;
    }
}
