<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\ValidationException;
use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\FieldParser;
use App\Domain\Dose\PeptideCatalog;
use App\Domain\Dose\UserPeptideService;
use PDO;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class UserPeptideServiceTest extends TestCase
{
    public function testCreateListsAfterCatalogAndRejectsDuplicates(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();

        $created = $service->create($pdo, FieldParser::from(['name' => 'Cagrilintide']));
        $this->assertSame('Cagrilintide', $created['name']);
        $this->assertSame('cagrilintide', $created['slug']);
        $this->assertSame(UserPeptideService::CUSTOM_SORT_ORDER, $created['sort_order']);

        $names = array_map(static fn (array $row): string => $row['name'], $service->listAll($pdo));
        $this->assertSame(['Tirzepatide', 'Cagrilintide'], $names);

        $this->assertSame($created['id'], $service->require($pdo, $created['id'])['id']);
        $this->assertSame('tirzepatide', $service->require($pdo, 'tirzepatide')['id']);

        try {
            $service->create($pdo, FieldParser::from(['name' => 'tirzepatide']));
            $this->fail('expected duplicate catalog name to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['name' => [DoseConfig::PEPTIDE_NAME_TAKEN]], $e->fields());
        }

        try {
            $service->create($pdo, FieldParser::from(['name' => 'Cagrilintide']));
            $this->fail('expected duplicate custom name to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['name' => [DoseConfig::PEPTIDE_NAME_TAKEN]], $e->fields());
        }
    }

    public function testSlugCollisionAndEmptySlugGetUniqueSuffix(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        $first = $service->create($pdo, FieldParser::from(['name' => 'Cagri']));
        $this->assertSame('cagri', $first['slug']);

        $collision = $service->create($pdo, FieldParser::from(['name' => 'Cagri!!!']));
        $this->assertNotSame('cagri', $collision['slug']);
        $this->assertStringStartsWith('cagri-', $collision['slug']);

        $blank = $service->create($pdo, FieldParser::from(['name' => '!!!']));
        $this->assertSame('peptide', $blank['slug']);
        $again = $service->create($pdo, FieldParser::from(['name' => '???']));
        $this->assertStringStartsWith('peptide-', $again['slug']);
    }

    public function testNameTooLongAndUnknownId(): void
    {
        $pdo = $this->pdo();
        $service = $this->service();
        try {
            $service->create($pdo, FieldParser::from(['name' => str_repeat('a', 81)]));
            $this->fail('expected long name to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['name' => [DoseConfig::PEPTIDE_NAME_TOO_LONG]], $e->fields());
        }

        try {
            $service->require($pdo, 'missing');
            $this->fail('expected unknown peptide to fail');
        } catch (ValidationException $e) {
            $this->assertSame(['peptide_type_id' => [DoseConfig::PEPTIDE_UNKNOWN]], $e->fields());
        }
    }

    private function service(): UserPeptideService
    {
        return new UserPeptideService($this->catalog(), new IdGenerator(), FrozenClock::at('2026-08-20T15:00:00Z'));
    }

    private function catalog(): PeptideCatalog
    {
        return new class implements PeptideCatalog {
            public function listActive(): array
            {
                return [[
                    'id' => 'tirzepatide',
                    'slug' => 'tirzepatide',
                    'name' => 'Tirzepatide',
                    'sort_order' => 2,
                ]];
            }

            public function findActiveById(string $id): ?array
            {
                return $id === 'tirzepatide' ? $this->listActive()[0] : null;
            }
        };
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE user_peptide_types (
                id TEXT PRIMARY KEY NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        return $pdo;
    }
}
