<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\SqliteFileMigrator;
use PDO;
use RuntimeException;
use Tests\TestCase;

class GlobalMigratorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-global-');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testMigrateCreatesSchemaAndSeedsPeptides(): void
    {
        $migrator = $this->migrator();
        $applied = $migrator->migrate();
        $this->assertSame(2, $applied);
        $this->assertFileExists($this->dir . '/global.sqlite');

        $pdo = $this->pdo();
        foreach (['users', 'sessions', 'peptide_types', 'oauth_states', 'schema_migrations'] as $table) {
            $this->assertTrue($this->tableExists($pdo, $table), $table . ' should exist');
        }

        $rows = $pdo->query(
            'SELECT slug, name, is_active, sort_order FROM peptide_types ORDER BY sort_order, slug'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['slug' => 'semaglutide', 'name' => 'Semaglutide', 'is_active' => 1, 'sort_order' => 1],
            ['slug' => 'tirzepatide', 'name' => 'Tirzepatide', 'is_active' => 1, 'sort_order' => 2],
            ['slug' => 'retatrutide', 'name' => 'Retatrutide', 'is_active' => 1, 'sort_order' => 3],
            ['slug' => 'liraglutide', 'name' => 'Liraglutide', 'is_active' => 1, 'sort_order' => 4],
        ], $this->intify($rows));
        $this->assertCount(4, $rows);
    }

    public function testSecondMigrateIsNoOp(): void
    {
        $migrator = $this->migrator();
        $this->assertSame(2, $migrator->migrate());
        $this->assertSame(0, $migrator->migrate());

        $pdo = $this->pdo();
        $versions = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        $peptides = (int) $pdo->query('SELECT COUNT(*) FROM peptide_types')->fetchColumn();
        $this->assertSame(2, $versions);
        $this->assertSame(4, $peptides);
    }

    public function testSeedSqlIsIdempotentIfReapplied(): void
    {
        $this->migrator()->migrate();
        $seed = dirname(__DIR__, 3) . '/migrations/global/002_seed_peptide_types.sql';
        $sql = file_get_contents($seed);
        $this->assertNotFalse($sql);
        $this->pdo()->exec($sql);
        $count = (int) $this->pdo()->query('SELECT COUNT(*) FROM peptide_types')->fetchColumn();
        $this->assertSame(4, $count);
    }

    public function testCreatesMissingDataDirectory(): void
    {
        $nested = $this->dir . '/nested/data';
        $migrator = new GlobalMigrator(
            new DataPaths($nested),
            dirname(__DIR__, 3) . '/migrations/global',
        );
        $migrator->migrate();
        $this->assertFileExists($nested . '/global.sqlite');
        $this->assertDirectoryExists($nested . '/users');
        $this->assertDirectoryExists($nested . '/tmp');
    }

    public function testMissingMigrationsDirectoryThrows(): void
    {
        $migrator = new GlobalMigrator(
            new DataPaths($this->dir),
            $this->dir . '/no-such-migrations',
        );
        $this->expectException(RuntimeException::class);
        $migrator->migrate();
    }

    public function testSqliteFileMigratorAppliesZeroPaddedFilesInOrder(): void
    {
        $mig = $this->dir . '/migs';
        mkdir($mig);
        file_put_contents($mig . '/001_create.sql', 'CREATE TABLE t (id TEXT PRIMARY KEY);');
        file_put_contents($mig . '/002_insert.sql', "INSERT INTO t (id) VALUES ('ok');");
        $sqlite = $this->dir . '/t.sqlite';
        $applied = (new SqliteFileMigrator())->migrate($sqlite, $mig);
        $this->assertSame(2, $applied);
        $pdo = new PDO('sqlite:' . $sqlite);
        $this->assertSame('ok', $pdo->query('SELECT id FROM t')->fetchColumn());
        $this->assertSame(0, (new SqliteFileMigrator())->migrate($sqlite, $mig));
    }

    public function testMigrateFailsWhenParentPathIsAFile(): void
    {
        $blocker = $this->dir . '/blocker';
        file_put_contents($blocker, 'nope');
        $mig = $this->dir . '/migs-block';
        mkdir($mig);
        file_put_contents($mig . '/001.sql', 'CREATE TABLE t (id TEXT);');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to create sqlite directory.');
        (new SqliteFileMigrator())->migrate($blocker . '/db.sqlite', $mig);
    }

    public function testMigrateFailsWhenSqlPathIsADirectory(): void
    {
        $mig = $this->dir . '/migs-dir';
        mkdir($mig);
        mkdir($mig . '/001.sql');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to read migration file.');
        (new SqliteFileMigrator())->migrate($this->dir . '/from-dir.sqlite', $mig);
    }

    private function migrator(): GlobalMigrator
    {
        return new GlobalMigrator(
            new DataPaths($this->dir),
            dirname(__DIR__, 3) . '/migrations/global',
        );
    }

    private function pdo(): PDO
    {
        return new PDO('sqlite:' . $this->dir . '/global.sqlite', null, null, [
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

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{slug: string, name: string, is_active: int, sort_order: int}>
     */
    private function intify(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'is_active' => (int) $row['is_active'],
                'sort_order' => (int) $row['sort_order'],
            ];
        }

        return $out;
    }
}
