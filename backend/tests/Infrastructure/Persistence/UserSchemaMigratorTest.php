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
        $this->assertSame(UserStoreFormat::V8LogBook, UserStoreFormat::current());
        $this->assertSame(8, UserStoreFormat::current()->value);
    }

    public function testCatalogAppliesStrategiesInVersionOrder(): void
    {
        $versions = array_map(
            static fn ($strategy): int => $strategy->version()->value,
            UserSchemaCatalog::default()->strategies(),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $versions);
        $this->assertCount(1, UserSchemaCatalog::through(UserStoreFormat::V1Initial)->strategies());
        $this->assertCount(2, UserSchemaCatalog::through(UserStoreFormat::V2BacAndSyringeStock)->strategies());
        $this->assertCount(4, UserSchemaCatalog::through(UserStoreFormat::V4NamedOpenVials)->strategies());
        $this->assertCount(5, UserSchemaCatalog::through(UserStoreFormat::V5ArchiveAndAdjustments)->strategies());
        $this->assertCount(6, UserSchemaCatalog::through(UserStoreFormat::V6Profiles)->strategies());
        $this->assertCount(7, UserSchemaCatalog::through(UserStoreFormat::V7SyringeArchive)->strategies());
        $this->assertCount(8, UserSchemaCatalog::through(UserStoreFormat::V8LogBook)->strategies());
        $this->assertDirectoryExists(UserSchemaCatalog::migrationsDirectory());
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/004_named_open_vials.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/005_archive_and_adjustments.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/006_profiles.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/007_syringe_archive.sql');
        $this->assertFileExists(UserSchemaCatalog::migrationsDirectory() . '/008_log_book.sql');
    }

    public function testFreshSqliteReachesCurrentFormat(): void
    {
        $path = $this->dir . '/fresh.sqlite';
        $applied = (new UserMigrator())->migrate($path);
        $this->assertSame(8, $applied);
        $this->assertSame(0, (new UserMigrator())->migrate($path));

        $pdo = $this->pdo($path);
        $this->assertSame(8, (new UserSchemaVersionDetector())->detect($pdo));
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
        $this->assertTrue($this->tableExists($pdo, 'people'));
        $this->assertTrue($this->tableExists($pdo, 'peptides'));
        $this->assertTrue($this->tableExists($pdo, 'account_syringe'));
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
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
        $this->assertSame(7, $applied);
        $pdo = $this->pdo($path);
        $this->assertSame(8, (new UserSchemaVersionDetector())->detect($pdo));
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

        $v8 = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $v8->exec('CREATE TABLE log_entries (id TEXT)');
        $this->assertSame(8, $detector->detect($v8));
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
        $this->assertSame(3, $applied);
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

    public function testV7FixtureBackfillsLogBookTables(): void
    {
        $path = $this->dir . '/v7-logbook.sqlite';
        (new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V6Profiles)))->migrate($path);
        $pdo = $this->pdo($path);
        $pdo->exec('DELETE FROM profiles WHERE is_default = 1');
        $noah = 'p-noah';
        $sam = 'p-sam';
        $pdo->exec(
            "INSERT INTO profiles (id, name, is_default, created_at) VALUES
             ('$noah', 'Noah', 0, '2026-01-01T00:00:00Z'),
             ('$sam', 'Sam', 0, '2026-01-02T00:00:00Z')"
        );
        $shared = 'c-shared';
        $newer = 'c-newer';
        $archived = 'c-archived';
        $pdo->exec(
            "INSERT INTO compounds (
                id, peptide_type_id, peptide_type_slug, peptide_type_name,
                peptide_mg, bac_water_ml, compounded_at, notes, created_at, name, is_open, archived_at
            ) VALUES
            ('$shared', 'tirzepatide', 'tirzepatide', 'Tirzepatide', 10, 2, '2026-08-10T10:00', NULL,
             '2026-08-10T10:00:00Z', 'Shared', 1, NULL),
            ('$newer', 'tirzepatide', 'tirzepatide', 'Tirzepatide', 8, 2, '2026-08-18T10:00', NULL,
             '2026-08-18T10:00:00Z', 'Newer', 1, NULL),
            ('$archived', 'retatrutide', 'retatrutide', 'Retatrutide', 5, 1, '2026-07-01T10:00', NULL,
             '2026-07-01T10:00:00Z', 'Archived dose vial', 0, '2026-07-01T12:00:00Z')"
        );
        $pdo->exec(
            "INSERT INTO compound_profiles (compound_id, profile_id) VALUES
             ('$shared', '$noah'), ('$shared', '$sam'), ('$newer', '$noah'), ('$archived', '$sam')"
        );
        $pdo->exec(
            "INSERT INTO uses (
                id, compound_id, iu, syringe_id, syringe_label, syringe_volume_ml, syringe_capacity_iu,
                volume_ml, peptide_mg, used_at, notes, created_at, updated_at, profile_id
            ) VALUES (
                'u1', '$archived', 10, NULL, '0.5 mL / 50 IU', 0.5, 50,
                0.1, 0.5, '2026-07-02T10:00', NULL, '2026-07-02T10:00:00Z', '2026-07-02T10:00:00Z', '$sam'
            )"
        );
        $pdo->exec(
            "INSERT INTO compound_adjustments (id, compound_id, delta_mg, remaining_ml, notes, created_at)
             VALUES ('adj1', '$shared', -1, 1.5, NULL, '2026-08-11T10:00:00Z')"
        );
        $pdo->exec(
            "INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default, quantity)
             VALUES ('sy1', '0.5 mL / 50 IU', 0.5, 50, 1, 25)"
        );
        $pdo->exec(
            "INSERT INTO bac_bottles (id, volume_ml, opened_at, created_at, remaining_ml, archived_at)
             VALUES ('bac1', 30, '2026-08-01T00:00', '2026-08-01T00:00:00Z', 30, NULL)"
        );
        $pdo = null;

        (new UserMigrator())->migrate($path);
        $pdo = $this->pdo($path);
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn());
        $current = $pdo->query(
            "SELECT id, usage_offset_mg, display_name FROM vials WHERE superseded_at IS NULL"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(2, $current);
        $offset = $pdo->query(
            "SELECT usage_offset_mg FROM vials WHERE id = '$shared'"
        )->fetchColumn();
        $this->assertEqualsWithDelta(1.0, (float) $offset, 1e-9);
        $log = $pdo->query("SELECT vial_name, is_last_draw, lid_color_name FROM log_entries WHERE id = 'u1'")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Archived dose vial', $log['vial_name']);
        $this->assertSame(0, (int) $log['is_last_draw']);
        $this->assertSame('', $log['lid_color_name']);
        $samVial = $pdo->query(
            "SELECT display_name FROM vials v
             INNER JOIN vial_people vp ON vp.vial_id = v.id
             WHERE vp.person_id = '$sam' AND v.superseded_at IS NULL"
        )->fetchColumn();
        $this->assertIsString($samVial);
        $this->assertStringNotContainsString('Noah', (string) $samVial);
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
