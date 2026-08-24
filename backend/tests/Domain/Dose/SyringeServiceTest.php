<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\SyringeService;
use PDO;
use Tests\TestCase;

class SyringeServiceTest extends TestCase
{
    public function testRestoreOneSkipsMissingAndIncrementsExisting(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE syringe_profiles (
                id TEXT PRIMARY KEY NOT NULL,
                label TEXT NOT NULL,
                volume_ml REAL NOT NULL,
                capacity_iu REAL NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                quantity INTEGER NOT NULL DEFAULT 0
            )'
        );
        $pdo->exec("INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
                    VALUES ('s1', '0.5 mL / 50 IU', 0.5, 50, 1, 2)");

        $service = new SyringeService(new IdGenerator());
        $service->restoreOne($pdo, null);
        $service->restoreOne($pdo, '');
        $service->restoreOne($pdo, 'gone');
        $service->restoreOne($pdo, 's1');
        $this->assertSame(3, $service->get($pdo, 's1')['quantity']);

        $created = $service->create($pdo, FieldParser::from([
            'volume_ml' => 1,
            'capacity_iu' => 40,
            'quantity' => 4,
        ]));
        $this->assertSame(4, $created['quantity']);

        $resized = $service->patch($pdo, 's1', FieldParser::from([
            'volume_ml' => 1,
            'capacity_iu' => 100,
        ]));
        $this->assertSame('1 mL / 100 IU', $resized['label']);

        $service->delete($pdo, 's1');
        $this->assertCount(1, $service->list($pdo));
        $this->assertTrue($service->get($pdo, $created['id'])['is_default']);

        try {
            $service->delete($pdo, $created['id']);
            $this->fail('expected last syringe delete to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['id' => [DoseConfig::SYRINGE_LAST]], $e->fields());
        }
    }

    public function testConsumeOneDebitsStockAndRejectsEmpty(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE syringe_profiles (
                id TEXT PRIMARY KEY NOT NULL,
                label TEXT NOT NULL,
                volume_ml REAL NOT NULL,
                capacity_iu REAL NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                quantity INTEGER NOT NULL DEFAULT 0
            )'
        );
        $pdo->exec("INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
                    VALUES ('s1', '0.5 mL / 50 IU', 0.5, 50, 1, 1)");

        $service = new SyringeService(new IdGenerator());
        $service->consumeOne($pdo, 's1');
        $this->assertSame(0, $service->get($pdo, 's1')['quantity']);
        $this->assertNull($service->fallbackProfile()['id']);
        $this->assertSame(DoseConfig::FALLBACK_SYRINGE_VOLUME_ML, $service->fallbackProfile()['volume_ml']);

        try {
            $service->consumeOne($pdo, 's1');
            $this->fail('expected empty stock to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['syringe_id' => [DoseConfig::SYRINGE_STOCK_EMPTY]], $e->fields());
        }
    }
}
