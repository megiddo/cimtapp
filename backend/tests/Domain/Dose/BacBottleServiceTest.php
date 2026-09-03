<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\BacBottleService;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use PDO;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class BacBottleServiceTest extends TestCase
{
    public function testCreditIgnoresMissingAndCapsAtVolume(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $bottle = $service->create($pdo, FieldParser::from(['volume_ml' => 10]));
        $service->debitCurrent($pdo, 2.0);
        $service->credit($pdo, 'missing', 4.0);
        $service->credit($pdo, null, 4.0);
        $service->credit($pdo, (string) $bottle['id'], 40.0);
        $this->assertEqualsWithDelta(10.0, $service->get($pdo, (string) $bottle['id'])['remaining_ml'], 1e-9);
    }

    public function testApplyMixDeltaUsesCurrentWhenBottleMissing(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $bottle = $service->create($pdo, FieldParser::from(['volume_ml' => 10, 'opened_at' => '2026-08-20T12:00']));
        $id = $service->applyMixDelta($pdo, 'gone', 1.0, 3.0);
        $this->assertSame($bottle['id'], $id);
        $this->assertEqualsWithDelta(8.0, $service->get($pdo, (string) $bottle['id'])['remaining_ml'], 1e-9);
        $this->assertSame($bottle['id'], $service->applyMixDelta($pdo, (string) $bottle['id'], 3.0, 3.0));
        $this->assertSame(
            $bottle['id'],
            $service->applyMixDelta($pdo, null, 3.0, 4.0)
        );
        $this->assertEqualsWithDelta(7.0, $service->current($pdo)['remaining_ml'], 1e-9);
        $this->assertNull($service->applyMixDelta($pdo, null, 4.0, 1.0));
        $this->assertEqualsWithDelta(7.0, $service->current($pdo)['remaining_ml'], 1e-9);
        $service->applyMixDelta($pdo, (string) $bottle['id'], 4.0, 2.0);
        $this->assertEqualsWithDelta(9.0, $service->get($pdo, (string) $bottle['id'])['remaining_ml'], 1e-9);
        $service->applyMixDelta($pdo, 'gone', 2.0, 1.0);
        $this->assertEqualsWithDelta(9.0, $service->get($pdo, (string) $bottle['id'])['remaining_ml'], 1e-9);
    }

    public function testApplyMixDeltaWithoutBottleSkipsDebit(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $this->assertNull($service->applyMixDelta($pdo, null, 0.0, 2.0));
        $this->assertSame([], $service->list($pdo));
    }

    public function testBurnDebitsRemainingAndRejectsOverdraw(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $bottle = $service->create($pdo, FieldParser::from(['volume_ml' => 10]));
        $burned = $service->burn($pdo, (string) $bottle['id'], FieldParser::from(['ml' => 1.5]));
        $this->assertEqualsWithDelta(8.5, $burned['remaining_ml'], 1e-9);

        try {
            $service->burn($pdo, (string) $bottle['id'], FieldParser::from(['ml' => 20]));
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ml', $e->fields());
        }

        try {
            $service->burn($pdo, 'missing', FieldParser::from(['ml' => 1]));
            $this->fail('expected');
        } catch (\App\Domain\DomainException\DomainRecordNotFoundException $e) {
            $this->assertSame(DoseConfig::BAC_UNKNOWN, $e->getMessage());
        }
    }

    public function testArchiveHidesEmptyBottle(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $bottle = $service->create($pdo, FieldParser::from(['volume_ml' => 10]));

        try {
            $service->archive($pdo, (string) $bottle['id']);
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['id' => [DoseConfig::ARCHIVE_NOT_EMPTY]], $e->fields());
        }

        $service->burn($pdo, (string) $bottle['id'], FieldParser::from(['ml' => 10]));
        $archived = $service->archive($pdo, (string) $bottle['id']);
        $this->assertNotNull($archived['archived_at']);
        $this->assertSame([], $service->list($pdo));
        $this->assertNotNull($service->get($pdo, (string) $bottle['id'])['archived_at']);

        try {
            $service->archive($pdo, (string) $bottle['id']);
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['id' => [DoseConfig::ALREADY_ARCHIVED]], $e->fields());
        }
    }

    private function service(): BacBottleService
    {
        return new BacBottleService(new DoseCalculator(), new IdGenerator(), FrozenClock::at('2026-08-20T15:00:00Z'));
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE bac_bottles (
                id TEXT PRIMARY KEY NOT NULL,
                volume_ml REAL NOT NULL,
                remaining_ml REAL NOT NULL,
                opened_at TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL,
                archived_at TEXT
            )'
        );

        return $pdo;
    }
}
