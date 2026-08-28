<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserSchema\UserSchemaCatalog;
use App\Infrastructure\Persistence\UserSchema\UserSchemaVersionDetector;
use App\Infrastructure\Persistence\UserSchema\UserStoreFormat;
use PDO;
use RuntimeException;
use Tests\TestCase;

class UserSchemaMigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-user-schema-');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testCurrentFormatIsArchiveAndAdjustments(): void
    {
        $this->assertSame(UserStoreFormat::V5ArchiveAndAdjustments, UserStoreFormat::current());
        $this->assertSame(5, UserStoreFormat::current()->value);
    }

    public function testCatalogAppliesStrategiesInVersionOrder(): void
    {
        $versions = array_map(
            static fn ($strategy): int => $strategy->version()->value,
            UserSchemaCatalog::default()->strategies(),
        );
        $this->assertSame([1, 2, 3, 4, 5], $versions);
        $this->assertCount(1, UserSchemaCatalog::through(UserStoreFormat::V1Initial)->strategies());
        $this->assertCount(2, UserSchemaCatalog::through(UserStoreFormat::V2BacAndSyringeStock)->strategies());
        $this->assertCount(4, UserSchemaCatalog::through(UserStoreFormat::V4NamedOpenVials)->strategies());
        $this->assertCount(5, UserSchemaCatalog::through(UserStoreFormat::V5ArchiveAndAdjustments)->strategies());
        $this->assertDirectoryExists(UserSchemaCatalog::migrationsDirectory());
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/004_named_open_vials.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/005_archive_and_adjustments.sql');
    }

    public function testFreshSqliteReachesCurrentFormat(): void
    {
        $path = $this->dir . '/fresh.sqlite';
        $applied = (new UserMigrator())->migrate($path);
        $this->assertSame(5, $applied);
        $this->assertSame(0, (new UserMigrator())->migrate($path));

        $pdo = $this->pdo($path);
        $this->assertSame(5, (new UserSchemaVersionDetector())->detect($pdo));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'name'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'is_open'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'archived_at'));
        $this->assertTrue($this->hasColumn($pdo, 'bac_bottles', 'archived_at'));
        $this->assertTrue($this->tableExists($pdo, 'compound_adjustments'));
        $this->assertTrue($this->tableExists($pdo, 'user_peptide_types'));
        $this->assertTrue($this->tableExists($pdo, 'bac_bottles'));
    }

    public function testDetectsLegacySchemaMigrationsAndMutatesForward(): void
    {
        $path = $this->dir . '/legacy.sqlite';
        (new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V1Initial)))->migrate($path);

        $pdo = $this->pdo($path);
        $pdo->exec(
            'CREATE TABLE schema_migrations (version TEXT PRIMARY KEY NOT NULL, applied_at TEXT NOT NULL)'
        );
        $pdo->exec("INSERT INTO schema_migrations VALUES ('001_create_schema.sql', 'now')");
        $pdo->exec('DROP TABLE user_store_format');
        $this->assertSame(1, (new UserSchemaVersionDetector())->detect($pdo));
        $pdo = null;

        $applied = (new UserMigrator())->migrate($path);
        $this->assertSame(4, $applied);
        $pdo = $this->pdo($path);
        $this->assertSame(5, (new UserSchemaVersionDetector())->detect($pdo));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'name'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'archived_at'));
    }

    public function testDetectsSchemaShapeWhenMigrationsTableIsMissing(): void
    {
        $detector = new UserSchemaVersionDetector();

        $empty = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->assertSame(0, $detector->detect($empty));

        $v1 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v1->exec('CREATE TABLE account (user_id TEXT)');
        $this->assertSame(1, $detector->detect($v1));

        $v2 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v2->exec('CREATE TABLE syringe_profiles (id TEXT, quantity INTEGER)');
        $this->assertSame(2, $detector->detect($v2));

        $v2b = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v2b->exec('CREATE TABLE bac_bottles (id TEXT)');
        $this->assertSame(2, $detector->detect($v2b));

        $v3 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v3->exec('CREATE TABLE user_peptide_types (id TEXT)');
        $this->assertSame(3, $detector->detect($v3));

        $v4 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v4->exec('CREATE TABLE compounds (id TEXT, name TEXT)');
        $this->assertSame(4, $detector->detect($v4));

        $v5 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v5->exec('CREATE TABLE compound_adjustments (id TEXT)');
        $this->assertSame(5, $detector->detect($v5));
    }

    public function testStoredFormatVersionWinsOverShape(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $detector = new UserSchemaVersionDetector();
        $detector->writeVersion($pdo, 2);
        $pdo->exec('CREATE TABLE compounds (id TEXT, name TEXT)');
        $this->assertSame(2, $detector->detect($pdo));

        $detector->writeVersion($pdo, 4);
        $this->assertSame(4, $detector->detect($pdo));

        $detector->writeVersion($pdo, 5);
        $this->assertSame(5, $detector->detect($pdo));
    }

    public function testEmptyFormatTableFallsThroughToShape(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE user_store_format (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                version INTEGER NOT NULL
            )'
        );
        $pdo->exec('CREATE TABLE compounds (id TEXT)');
        $this->assertSame(1, (new UserSchemaVersionDetector())->detect($pdo));
    }

    public function testNamedVialMutationCopiesPeptideName(): void
    {
        $path = $this->dir . '/named.sqlite';
        (new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V3UserPeptideTypes)))->migrate($path);
        $pdo = $this->pdo($path);
        $pdo->exec(
            "INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at
             ) VALUES (
                'c1', 'tirzepatide', 'tirzepatide', 'Tirzepatide',
                10, 2, '2026-08-20T12:00', NULL, '2026-08-20T12:00:00Z'
             )"
        );
        $pdo = null;

        (new UserMigrator())->migrate($path);
        $pdo = $this->pdo($path);
        $row = $pdo->query('SELECT name, is_open FROM compounds')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Tirzepatide', $row['name']);
        $this->assertSame(1, (int) $row['is_open']);
    }

    public function testMissingSqlFileThrows(): void
    {
        $empty = $this->dir . '/empty-migs';
        mkdir($empty);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read migration file.');
        (new UserMigrator(UserSchemaCatalog::default($empty)))->migrate($this->dir . '/fail.sqlite');
    }

    public function testMigrateFailsWhenParentPathIsAFile(): void
    {
        $blocker = $this->dir . '/blocker';
        file_put_contents($blocker, 'nope');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create sqlite directory.');
        (new UserMigrator())->migrate($blocker . '/db.sqlite');
    }

    public function testUnknownSchemaMigrationFilenamesAreIgnored(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec(
            'CREATE TABLE schema_migrations (version TEXT PRIMARY KEY NOT NULL, applied_at TEXT NOT NULL)'
        );
        $pdo->exec("INSERT INTO schema_migrations VALUES ('999_future.sql', 'now')");
        $this->assertSame(0, (new UserSchemaVersionDetector())->detect($pdo));
    }

    private function pdo(string $path): PDO
    {
        return new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1"
        );
        $stmt->execute([':name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if ((string) $row['name'] === $column) {
                return true;
            }
        }

        return false;
    }
}
