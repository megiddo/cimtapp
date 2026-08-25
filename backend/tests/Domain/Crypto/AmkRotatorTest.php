<?php

declare(strict_types=1);

namespace Tests\Domain\Crypto;

use App\Domain\Crypto\AmkRotator;
use App\Domain\Crypto\Crypto;
use App\Domain\Crypto\CryptoException;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalConnection;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\SqliteUserRepository;
use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserStore;
use PDO;
use Tests\TestCase;

class AmkRotatorTest extends TestCase
{
    private const OLD_KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
    private const NEW_KEY = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';
    private const USER_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->makeTempDir('cimtapp-amk-');
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
        parent::tearDown();
    }

    public function testRotateRewrapsDeksWithoutRewritingEncFiles(): void
    {
        $old = Crypto::fromMasterKey(self::OLD_KEY);
        $new = Crypto::fromMasterKey(self::NEW_KEY);
        $paths = new DataPaths($this->dir);
        (new GlobalMigrator($paths, dirname(__DIR__, 3) . '/migrations/global'))->migrate();
        $users = new SqliteUserRepository(new GlobalConnection($paths));
        $store = new UserStore($old, new UserMigrator(), $paths);

        $dek = $old->mintDek();
        $wrapped = $old->wrapDek($dek);
        $users->insert(new \App\Domain\Auth\User(
            self::USER_ID,
            'rotate@example.com',
            null,
            null,
            $wrapped->ciphertext(),
            $wrapped->nonce(),
            '2026-08-20T15:00:00Z',
            null,
        ));
        $store->create(self::USER_ID, $dek);
        $store->withUnlocked(self::USER_ID, $dek, function (PDO $pdo): void {
            $pdo->prepare(
                'INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
                 VALUES (?, ?, NULL, NULL, ?)'
            )->execute([self::USER_ID, 'rotate@example.com', '2026-08-20T15:00:00Z']);
        });
        $enc = $this->dir . '/users/' . self::USER_ID . '.sqlite.enc';
        $before = hash_file('sha256', $enc);

        $rotated = (new AmkRotator($users))->rotate($old, $new);
        $this->assertSame(1, $rotated);
        $this->assertSame($before, hash_file('sha256', $enc));

        $row = $users->findById(self::USER_ID);
        $this->assertNotNull($row);
        $opened = $new->unwrapDek($row->dekNonce, $row->encryptedDek);
        $this->assertSame($dek, $opened);
        $email = $store->withUnlocked(self::USER_ID, $opened, function (PDO $pdo): string {
            return (string) $pdo->query('SELECT email FROM account')->fetchColumn();
        });
        $this->assertSame('rotate@example.com', $email);

        $this->expectException(CryptoException::class);
        $old->unwrapDek($row->dekNonce, $row->encryptedDek);
    }

    public function testRotateEmptyUserTableIsZero(): void
    {
        $paths = new DataPaths($this->dir);
        (new GlobalMigrator($paths, dirname(__DIR__, 3) . '/migrations/global'))->migrate();
        $users = new SqliteUserRepository(new GlobalConnection($paths));
        $from = Crypto::fromMasterKey(self::OLD_KEY);
        $to = Crypto::fromMasterKey(self::NEW_KEY);
        $this->assertSame(0, (new AmkRotator($users))->rotate($from, $to));
        $this->assertSame([], $users->listAll());
    }
}
