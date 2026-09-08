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

    public function testCurrentFormatIsProfiles(): void
    {
        $this->assertSame(UserStoreFormat::V7SyringeArchive, UserStoreFormat::current());
        $this->assertSame(7, UserStoreFormat::current()->value);
    }

    public function testCatalogAppliesStrategiesInVersionOrder(): void
    {
        $versions = array_map(
            static fn ($strategy): int => $strategy->version()->value,
            UserSchemaCatalog::default()->strategies(),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $versions);
        $this->assertCount(1, UserSchemaCatalog::through(UserStoreFormat::V1Initial)->strategies());
        $this->assertCount(2, UserSchemaCatalog::through(UserStoreFormat::V2BacAndSyringeStock)->strategies());
        $this->assertCount(4, UserSchemaCatalog::through(UserStoreFormat::V4NamedOpenVials)->strategies());
        $this->assertCount(5, UserSchemaCatalog::through(UserStoreFormat::V5ArchiveAndAdjustments)->strategies());
        $this->assertCount(6, UserSchemaCatalog::through(UserStoreFormat::V6Profiles)->strategies());
        $this->assertCount(7, UserSchemaCatalog::through(UserStoreFormat::V7SyringeArchive)->strategies());
        $this->assertDirectoryExists(UserSchemaCatalog::migrationsDirectory());
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/004_named_open_vials.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/005_archive_and_adjustments.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/006_profiles.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/007_syringe_archive.sql');
    }

    public function testFreshSqliteReachesCurrentFormat(): void
    {
        $path = $this->dir . '/fresh.sqlite';
        $applied = (new UserMigrator())->migrate($path);
        $this->assertSame(7, $applied);
        $this->assertSame(0, (new UserMigrator())->migrate($path));

        $pdo = $this->pdo($path);
        $this->assertSame(7, (new UserSchemaVersionDetector())->detect($pdo));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'name'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'is_open'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'archived_at'));
        $this->assertTrue($this->hasColumn($pdo, 'bac_bottles', 'archived_at'));
        $this->assertTrue($this->hasColumn($pdo, 'syringe_profiles', 'archived_at'));
        $this->assertTrue($this->hasColumn($pdo, 'uses', 'profile_id'));
        $this->assertTrue($this->tableExists($pdo, 'compound_adjustments'));
        $this->assertTrue($this->tableExists($pdo, 'user_peptide_types'));
        $this->assertTrue($this->tableExists($pdo, 'bac_bottles'));
        $this->assertTrue($this->tableExists($pdo, 'profiles'));
        $this->assertTrue($this->tableExists($pdo, 'compound_profiles'));
        $default = $pdo->query('SELECT name, is_default FROM profiles')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Default', $default['name']);
        $this->assertSame(1, (int) $default['is_default']);
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
        $this->assertSame(6, $applied);
        $pdo = $this->pdo($path);
        $this->assertSame(7, (new UserSchemaVersionDetector())->detect($pdo));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'name'));
        $this->assertTrue($this->hasColumn($pdo, 'compounds', 'archived_at'));
        $this->assertTrue($this->tableExists($pdo, 'profiles'));
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

        $v6 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v6->exec('CREATE TABLE profiles (id TEXT)');
        $this->assertSame(6, $detector->detect($v6));

        $v7 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v7->exec('CREATE TABLE syringe_profiles (id TEXT, archived_at TEXT)');
        $this->assertSame(7, $detector->detect($v7));
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

        $detector->writeVersion($pdo, 6);
        $this->assertSame(6, $detector->detect($pdo));

        $detector->writeVersion($pdo, 7);
        $this->assertSame(7, $detector->detect($pdo));
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
        $linked = $pdo->query('SELECT COUNT(*) FROM compound_profiles')->fetchColumn();
        $this->assertSame(1, (int) $linked);
    }

    public function testProfilesMutationSeedsDefaultAndBackfillsUses(): void
    {
        $path = $this->dir . '/profiles.sqlite';
        (new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V5ArchiveAndAdjustments)))->migrate($path);
        $pdo = $this->pdo($path);
        $pdo->exec(
            "INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at, name, is_open
             ) VALUES (
                'c1', 'tirzepatide', 'tirzepatide', 'Tirzepatide',
                10, 2, '2026-08-20T12:00', NULL, '2026-08-20T12:00:00Z', 'Tirzepatide', 1
             )"
        );
        $pdo->exec(
            "INSERT INTO uses (
                id, compound_id, iu, syringe_id, syringe_label, syringe_volume_ml, syringe_capacity_iu,
                volume_ml, peptide_mg, used_at, notes, created_at, updated_at
             ) VALUES (
                'u1', 'c1', 25, NULL, '0.5 mL / 50 IU', 0.5, 50,
                0.25, 1.25, '2026-08-20T16:00', NULL, '2026-08-20T16:00:00Z', '2026-08-20T16:00:00Z'
             )"
        );
        $pdo = null;

        $applied = (new UserMigrator())->migrate($path);
        $this->assertSame(2, $applied);
        $pdo = $this->pdo($path);
        $profile = $pdo->query('SELECT id, name, is_default FROM profiles')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Default', $profile['name']);
        $this->assertSame(1, (int) $profile['is_default']);
        $useProfile = $pdo->query("SELECT profile_id FROM uses WHERE id = 'u1'")->fetchColumn();
        $this->assertSame($profile['id'], $useProfile);
        $compoundProfile = $pdo->query(
            "SELECT profile_id FROM compound_profiles WHERE compound_id = 'c1'"
        )->fetchColumn();
        $this->assertSame($profile['id'], $compoundProfile);
    }

    public function testSyringeArchiveMutationBackfillsClosedVials(): void
    {
        $path = $this->dir . '/syringe-archive.sqlite';
        (new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V6Profiles)))->migrate($path);
        $pdo = $this->pdo($path);
        $pdo->exec(
            "INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
             VALUES ('s1', '0.5 mL / 50 IU', 0.5, 50, 1, 0)"
        );
        $pdo->exec(
            "INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at, name, is_open, archived_at
             ) VALUES
             (
                'empty-closed', 'tirzepatide', 'tirzepatide', 'Tirzepatide',
                10, 2, '2026-08-20T12:00', NULL, '2026-08-20T12:00:00Z', 'Empty closed', 0, NULL
             ),
             (
                'stock-closed', 'tirzepatide', 'tirzepatide', 'Tirzepatide',
                10, 2, '2026-08-20T12:00', NULL, '2026-08-20T12:00:00Z', 'Stock closed', 0, NULL
             ),
             (
                'already-archived', 'tirzepatide', 'tirzepatide', 'Tirzepatide',
                10, 2, '2026-08-20T12:00', NULL, '2026-08-20T12:00:00Z', 'Already archived', 0, '2026-01-01T00:00:00Z'
             )"
        );
        $pdo->exec(
            "INSERT INTO compound_adjustments (id, compound_id, delta_mg, remaining_ml, notes, created_at)
             VALUES ('adj1', 'empty-closed', -10, 0, NULL, '2026-08-20T12:00:00Z')"
        );
        $pdo = null;

        (new UserMigrator())->migrate($path);
        $pdo = $this->pdo($path);
        $this->assertTrue($this->hasColumn($pdo, 'syringe_profiles', 'archived_at'));
        $syringeArchived = $pdo->query("SELECT archived_at FROM syringe_profiles WHERE id = 's1'")->fetchColumn();
        $this->assertNull($syringeArchived);

        $empty = $pdo->query(
            "SELECT is_open, archived_at FROM compounds WHERE id = 'empty-closed'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $empty['is_open']);
        $this->assertNotNull($empty['archived_at']);

        $stock = $pdo->query(
            "SELECT is_open, archived_at FROM compounds WHERE id = 'stock-closed'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $stock['is_open']);
        $this->assertNull($stock['archived_at']);

        $archived = $pdo->query(
            "SELECT is_open, archived_at FROM compounds WHERE id = 'already-archived'"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(0, (int) $archived['is_open']);
        $this->assertSame('2026-01-01T00:00:00Z', $archived['archived_at']);
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
