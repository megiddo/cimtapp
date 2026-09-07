<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Dose\BacStock;
use App\Domain\Dose\CompoundStock;
use App\Domain\Dose\SyringeStock;
use PDO;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class StockAdaptersTest extends TestCase
{
    public function testAdaptersExposeKindRemainingAndPersistArchive(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE compounds (id TEXT PRIMARY KEY, is_open INTEGER, archived_at TEXT)');
        $pdo->exec('CREATE TABLE bac_bottles (id TEXT PRIMARY KEY, archived_at TEXT)');
        $pdo->exec('CREATE TABLE syringe_profiles (id TEXT PRIMARY KEY, archived_at TEXT)');
        $pdo->exec("INSERT INTO compounds (id, is_open, archived_at) VALUES ('c1', 1, NULL)");
        $pdo->exec("INSERT INTO bac_bottles (id, archived_at) VALUES ('b1', NULL)");
        $pdo->exec("INSERT INTO syringe_profiles (id, archived_at) VALUES ('s1', NULL)");

        $clock = FrozenClock::at('2026-08-27T16:00:00Z');
        $compound = new CompoundStock($pdo, ['id' => 'c1', 'remaining_mg' => 0.0, 'archived_at' => null]);
        $bac = new BacStock($pdo, ['id' => 'b1', 'remaining_ml' => 0.0, 'archived_at' => null]);
        $syringe = new SyringeStock($pdo, ['id' => 's1', 'quantity' => 0, 'archived_at' => null]);

        $this->assertSame('c1', $compound->id());
        $this->assertSame('compound', $compound->kind());
        $this->assertSame(0.0, $compound->remaining());
        $this->assertFalse($compound->isArchived());
        $compound->archive($clock);
        $this->assertSame(
            '2026-08-27T16:00:00Z',
            $pdo->query("SELECT archived_at FROM compounds WHERE id = 'c1'")->fetchColumn(),
        );
        $this->assertSame(0, (int) $pdo->query("SELECT is_open FROM compounds WHERE id = 'c1'")->fetchColumn());

        $this->assertSame('bac', $bac->kind());
        $this->assertSame(0.0, $bac->remaining());
        $this->assertFalse($bac->isArchived());
        $bac->archive($clock);
        $this->assertSame(
            '2026-08-27T16:00:00Z',
            $pdo->query("SELECT archived_at FROM bac_bottles WHERE id = 'b1'")->fetchColumn(),
        );

        $this->assertSame('syringe', $syringe->kind());
        $this->assertSame(0.0, $syringe->remaining());
        $this->assertFalse($syringe->isArchived());
        $syringe->archive($clock);
        $this->assertSame(
            '2026-08-27T16:00:00Z',
            $pdo->query("SELECT archived_at FROM syringe_profiles WHERE id = 's1'")->fetchColumn(),
        );

        $this->assertTrue(
            (new CompoundStock($pdo, ['id' => 'c1', 'remaining_mg' => 1.5, 'archived_at' => 'x']))->isArchived()
        );
        $this->assertSame(1.5, (new CompoundStock($pdo, ['id' => 'c1', 'remaining_mg' => 1.5, 'archived_at' => null]))->remaining());
        $this->assertTrue((new BacStock($pdo, ['id' => 'b1', 'remaining_ml' => 2.0, 'archived_at' => 'x']))->isArchived());
        $this->assertSame(2.0, (new BacStock($pdo, ['id' => 'b1', 'remaining_ml' => 2.0, 'archived_at' => null]))->remaining());
        $this->assertTrue((new SyringeStock($pdo, ['id' => 's1', 'quantity' => 3, 'archived_at' => 'x']))->isArchived());
        $this->assertSame(3.0, (new SyringeStock($pdo, ['id' => 's1', 'quantity' => 3, 'archived_at' => null]))->remaining());
    }
}
