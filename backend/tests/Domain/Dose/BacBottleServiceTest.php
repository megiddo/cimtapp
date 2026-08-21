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

    public function testApplyMixDeltaWithoutBottleThrows(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        try {
            $service->applyMixDelta($pdo, null, 0.0, 2.0);
            $this->fail('expected');
        } catch (ValidationException $e) {
            $this->assertSame(['bac_water_ml' => [DoseConfig::NO_BAC_BOTTLE]], $e->fields());
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
                created_at TEXT NOT NULL
            )'
        );

        return $pdo;
    }
}
