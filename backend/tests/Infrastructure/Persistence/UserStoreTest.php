<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence;

use App\Domain\Crypto\Crypto;
use App\Domain\Crypto\CryptoException;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserSchema\UserSchemaCatalog;
use App\Infrastructure\Persistence\UserSchema\UserStoreFormat;
use App\Infrastructure\Persistence\UserStore;
use App\Infrastructure\Persistence\UserStoreException;
use App\Infrastructure\Persistence\UserStoreLockedException;
use PDO;
use Tests\TestCase;

class UserStoreTest extends TestCase
{
    private const HEX_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const USER_ID = '11111111-1111-4111-8111-111111111111';

    private string $dir;
    private Crypto $crypto;
    private UserStore $store;
    private string $dek;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-user-');
        $this->crypto = Crypto::fromMasterKey(self::HEX_KEY);
        $this->store = $this->makeStore();
        $this->dek = $this->crypto->mintDek();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testCreateWritesEncryptedStoreWithoutLeavingPlaintext(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $this->assertFileExists($enc);
        $this->assertNotSame(0, filesize($enc));
        $this->assertSame([], glob($this->dir . '/tmp/*.sqlite') ?: []);

        $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            foreach (['account', 'syringe_profiles', 'compounds', 'uses', 'bac_bottles', 'user_peptide_types', 'compound_adjustments', 'user_store_format'] as $table) {
                $stmt = $pdo->prepare(
                    "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name"
                );
                $stmt->execute([':name' => $table]);
                $this->assertNotFalse($stmt->fetchColumn(), $table . ' missing');
            }
            $version = (int) $pdo->query('SELECT version FROM user_store_format WHERE id = 1')->fetchColumn();
            $this->assertSame(UserStoreFormat::current()->value, $version);
            $names = array_map(
                static fn (array $col): string => (string) $col['name'],
                $pdo->query('PRAGMA table_info(compounds)')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            );
            $this->assertContains('name', $names);
            $this->assertContains('is_open', $names);
            $this->assertContains('archived_at', $names);
        });
    }

    public function testUnlockAppliesPendingUserMigrations(): void
    {
        $legacy = new UserStore(
            $this->crypto,
            new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V1Initial)),
            new DataPaths($this->dir),
        );
        $legacy->create(self::USER_ID, $this->dek);
        $legacy->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            $names = array_map(
                static fn (array $col): string => (string) $col['name'],
                $pdo->query('PRAGMA table_info(syringe_profiles)')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            );
            $this->assertNotContains('quantity', $names);
            $missing = $pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'bac_bottles'"
            );
            $this->assertFalse($missing === false ? false : $missing->fetchColumn());
        });

        $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            $names = array_map(
                static fn (array $col): string => (string) $col['name'],
                $pdo->query('PRAGMA table_info(syringe_profiles)')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            );
            $this->assertContains('quantity', $names);
            $found = $pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'bac_bottles'"
            );
            $this->assertNotFalse($found === false ? false : $found->fetchColumn());
            $peptides = $pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'user_peptide_types'"
            );
            $this->assertNotFalse($peptides === false ? false : $peptides->fetchColumn());
            $compoundCols = array_map(
                static fn (array $col): string => (string) $col['name'],
                $pdo->query('PRAGMA table_info(compounds)')->fetchAll(PDO::FETCH_ASSOC) ?: [],
            );
            $this->assertContains('name', $compoundCols);
            $this->assertContains('is_open', $compoundCols);
            $this->assertContains('archived_at', $compoundCols);
            $this->assertSame(
                UserStoreFormat::current()->value,
                (int) $pdo->query('SELECT version FROM user_store_format WHERE id = 1')->fetchColumn(),
            );
        });
    }

    public function testWriteThenReopenPersistsRow(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            $pdo->prepare(
                'INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
                 VALUES (?, ?, NULL, NULL, ?)'
            )->execute([self::USER_ID, 'user@example.com', '2026-01-01T00:00:00+00:00']);
        });

        $email = $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): string {
            $value = $pdo->query('SELECT email FROM account')->fetchColumn();
            $this->assertNotFalse($value);

            return (string) $value;
        });
        $this->assertSame('user@example.com', $email);
        $this->assertSame([], glob($this->dir . '/tmp/*.sqlite') ?: []);
    }

    public function testConcurrentWritersSerialize(): void
    {
        $this->store->create(self::USER_ID, $this->dek);

        $a = $this->startWriter(200, 'writer-a');
        $this->waitForLocked($a);
        $b = $this->startWriter(50, 'writer-b');

        $codeA = $this->finishWriter($a);
        $codeB = $this->finishWriter($b);
        $this->assertSame(0, $codeA, $a['stderr']);
        $this->assertSame(0, $codeB, $b['stderr']);

        $count = $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): int {
            return (int) $pdo->query('SELECT COUNT(*) FROM syringe_profiles')->fetchColumn();
        });
        $this->assertSame(2, $count);
    }

    public function testLockTimeoutThrowsUserStoreLockedException(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $holder = $this->startWriter(800, 'holder');
        $this->waitForLocked($holder);

        $contended = $this->makeStore(80);
        try {
            $contended->withUnlocked(self::USER_ID, $this->dek, static fn (PDO $pdo): int => 1);
            $this->fail('Expected UserStoreLockedException');
        } catch (UserStoreLockedException $e) {
            $this->assertSame('User store is locked.', $e->getMessage());
        } finally {
            $this->finishWriter($holder);
        }
    }

    public function testFailedDecryptLeavesCiphertextUntouched(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $before = hash_file('sha256', $enc);
        $wrong = $this->crypto->mintDek();

        try {
            $this->store->withUnlocked(self::USER_ID, $wrong, static fn (PDO $pdo): int => 1);
            $this->fail('Expected CryptoException');
        } catch (CryptoException) {
            $this->assertSame($before, hash_file('sha256', $enc));
        }
        $this->assertSame([], glob($this->dir . '/tmp/*.sqlite') ?: []);
    }

    public function testCallbackFailureLeavesCiphertextUntouched(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $before = hash_file('sha256', $enc);

        try {
            $this->store->withUnlocked(self::USER_ID, $this->dek, static function (PDO $pdo): void {
                $pdo->exec(
                    "INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
                     VALUES ('x', 'lost@example.com', NULL, NULL, 'now')"
                );
                throw new UserStoreException('boom');
            });
            $this->fail('Expected UserStoreException');
        } catch (UserStoreException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($before, hash_file('sha256', $enc));
        $email = $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): mixed {
            return $pdo->query('SELECT email FROM account')->fetchColumn();
        });
        $this->assertFalse($email);
    }

    public function testExportPlaintextReturnsSqliteAndShredsTmp(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $this->store->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            $pdo->prepare(
                'INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
                 VALUES (?, ?, NULL, NULL, ?)'
            )->execute([self::USER_ID, 'export@example.com', '2026-08-20T15:00:00Z']);
        });
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $before = hash_file('sha256', $enc);

        $bytes = $this->store->exportPlaintext(self::USER_ID, $this->dek);
        $this->assertStringStartsWith('SQLite format 3', $bytes);
        $this->assertSame($before, hash_file('sha256', $enc));
        $this->assertSame([], glob($this->dir . '/tmp/*.sqlite') ?: []);

        $tmp = $this->dir . '/read-export.sqlite';
        file_put_contents($tmp, $bytes);
        $pdo = new PDO('sqlite:' . $tmp);
        $this->assertSame('export@example.com', $pdo->query('SELECT email FROM account')->fetchColumn());
    }

    public function testExportDecryptsLegacyStoreAndMutatesToCurrentFormat(): void
    {
        $legacy = new UserStore(
            $this->crypto,
            new UserMigrator(UserSchemaCatalog::through(UserStoreFormat::V1Initial)),
            new DataPaths($this->dir),
        );
        $legacy->create(self::USER_ID, $this->dek);
        $legacy->withUnlocked(self::USER_ID, $this->dek, function (PDO $pdo): void {
            $pdo->prepare(
                'INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
                 VALUES (?, ?, NULL, NULL, ?)'
            )->execute([self::USER_ID, 'legacy@example.com', '2026-08-20T15:00:00Z']);
        });
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $before = hash_file('sha256', $enc);

        $bytes = $this->store->exportPlaintext(self::USER_ID, $this->dek);
        $this->assertStringStartsWith('SQLite format 3', $bytes);
        $this->assertNotSame($before, hash_file('sha256', $enc));
        $this->assertSame([], glob($this->dir . '/tmp/*.sqlite') ?: []);

        $tmp = $this->dir . '/legacy-export.sqlite';
        file_put_contents($tmp, $bytes);
        $pdo = new PDO('sqlite:' . $tmp);
        $this->assertSame('legacy@example.com', $pdo->query('SELECT email FROM account')->fetchColumn());
        $this->assertSame(
            UserStoreFormat::current()->value,
            (int) $pdo->query('SELECT version FROM user_store_format WHERE id = 1')->fetchColumn(),
        );
        $cols = array_map(
            static fn (array $col): string => (string) $col['name'],
            $pdo->query('PRAGMA table_info(compounds)')->fetchAll(PDO::FETCH_ASSOC) ?: [],
        );
        $this->assertContains('name', $cols);
        $this->assertContains('is_open', $cols);
        $this->assertContains('archived_at', $cols);
    }

    public function testExportMissingStoreFails(): void
    {
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('User store not found.');
        $this->store->exportPlaintext(self::USER_ID, $this->dek);
    }

    public function testCreateTwiceFails(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('User store already exists.');
        $this->store->create(self::USER_ID, $this->dek);
    }

    public function testOpenMissingStoreFails(): void
    {
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('User store not found.');
        $this->store->withUnlocked(self::USER_ID, $this->dek, static fn (PDO $pdo): int => 1);
    }

    public function testRejectsNonUuidUserId(): void
    {
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('User id must be a UUID.');
        $this->store->create('../escape', $this->dek);
    }

    public function testRejectsZeroLockTimeout(): void
    {
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('Lock timeout must be at least 1 ms.');
        $this->makeStore(0);
    }

    public function testDataPathsLayoutAndValidation(): void
    {
        $paths = new DataPaths($this->dir . '/');
        $this->assertSame($this->dir, $paths->root());
        $this->assertSame($this->dir . '/global.sqlite', $paths->globalSqlite());
        $this->assertSame($this->dir . '/users', $paths->usersDir());
        $this->assertSame($this->dir . '/tmp', $paths->tmpDir());
        $this->assertSame(
            $this->dir . '/users/' . self::USER_ID . '.sqlite.enc',
            $paths->userEnc(self::USER_ID)
        );
        $this->assertSame(
            $this->dir . '/users/' . self::USER_ID . '.lock',
            $paths->userLock(self::USER_ID)
        );
        $this->assertSame(
            $this->dir . '/users/' . self::USER_ID . '.sqlite.enc.tmp',
            $paths->userEncStaging(self::USER_ID)
        );

        $this->expectException(\RuntimeException::class);
        new DataPaths("bad\0path");
    }

    public function testDataPathsRejectsEmpty(): void
    {
        $this->expectException(\RuntimeException::class);
        new DataPaths('');
    }

    public function testEnsureFailsWhenDataDirIsAFile(): void
    {
        $blocker = $this->dir . '/not-a-dir';
        file_put_contents($blocker, 'x');
        $paths = new DataPaths($blocker);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create data directory.');
        $paths->ensure();
    }

    public function testCreateFailsWhenEncPathIsADirectory(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        unlink($enc);
        mkdir($enc);
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('Failed to persist user store.');
        $this->store->create(self::USER_ID, $this->dek);
    }

    public function testPersistFailsWhenEncPathBecomesADirectory(): void
    {
        $this->store->create(self::USER_ID, $this->dek);
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('Failed to persist user store.');
        $this->store->withUnlocked(self::USER_ID, $this->dek, function () use ($enc): void {
            unlink($enc);
            mkdir($enc);
        });
    }

    public function testLockOpenFailsWhenLockPathIsADirectory(): void
    {
        $this->pathsEnsureUsers();
        mkdir($this->dir . '/users/' . self::USER_ID . '.lock', 0700, true);
        $this->expectException(UserStoreException::class);
        $this->expectExceptionMessage('Unable to open user store lock.');
        $this->store->create(self::USER_ID, $this->dek);
    }

    private function pathsEnsureUsers(): void
    {
        (new DataPaths($this->dir))->ensure();
    }

    private function makeStore(int $lockTimeoutMs = 5000): UserStore
    {
        return new UserStore(
            $this->crypto ?? Crypto::fromMasterKey(self::HEX_KEY),
            new UserMigrator(),
            new DataPaths($this->dir),
            $lockTimeoutMs,
        );
    }

    /**
     * @return array{process: resource, pipes: array<int, resource>, stderr: string}
     */
    private function startWriter(int $sleepMs, string $label): array
    {
        $cmd = [
            PHP_BINARY,
            dirname(__DIR__, 2) . '/fixtures/user-store-writer.php',
            $this->dir,
            self::USER_ID,
            bin2hex($this->dek),
            self::HEX_KEY,
            (string) $sleepMs,
            $label,
        ];
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($cmd, $spec, $pipes);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        return ['process' => $process, 'pipes' => $pipes, 'stderr' => ''];
    }

    /**
     * @param array{process: resource, pipes: array<int, resource>, stderr: string} $writer
     */
    private function waitForLocked(array &$writer): void
    {
        $buf = '';
        $deadline = microtime(true) + 3;
        while (microtime(true) < $deadline) {
            $chunk = fread($writer['pipes'][1], 1024);
            if (is_string($chunk) && $chunk !== '') {
                $buf .= $chunk;
                if (str_contains($buf, 'locked')) {
                    return;
                }
            }
            usleep(5000);
        }
        $writer['stderr'] = (string) stream_get_contents($writer['pipes'][2]);
        $this->fail('Writer never acquired lock: ' . $buf . ' ' . $writer['stderr']);
    }

    /**
     * @param array{process: resource, pipes: array<int, resource>, stderr: string} $writer
     */
    private function finishWriter(array $writer): int
    {
        $deadline = microtime(true) + 5;
        $status = ['running' => true];
        while (microtime(true) < $deadline) {
            $status = proc_get_status($writer['process']);
            if ($status['running'] === false) {
                break;
            }
            usleep(10000);
        }
        $stderr = (string) stream_get_contents($writer['pipes'][2]);
        fclose($writer['pipes'][1]);
        fclose($writer['pipes'][2]);
        if ($status['running']) {
            proc_terminate($writer['process']);
            proc_close($writer['process']);
            $this->fail('Writer did not exit: ' . $stderr);
        }
        proc_close($writer['process']);

        return (int) $status['exitcode'];
    }
}
