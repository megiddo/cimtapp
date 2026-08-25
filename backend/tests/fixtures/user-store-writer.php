<?php

declare(strict_types=1);

/**
 * Holds an exclusive user-store lock, then inserts a syringe_profiles row.
 * Used by UserStoreTest to prove flock serializes writers across processes.
 *
 * argv: dataDir userId dekHex masterKey sleepMs label
 */

use App\Domain\Crypto\Crypto;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserStore;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argc < 7) {
    fwrite(STDERR, "usage: dataDir userId dekHex masterKey sleepMs label\n");
    exit(2);
}

[, $dataDir, $userId, $dekHex, $masterKey, $sleepMs, $label] = $argv;

stream_set_write_buffer(STDOUT, 0);

$dek = sodium_hex2bin($dekHex);
if (!is_string($dek) || strlen($dek) !== 32) {
    fwrite(STDERR, "invalid dek hex\n");
    exit(2);
}

$store = new UserStore(
    Crypto::fromMasterKey($masterKey),
    new UserMigrator(),
    new DataPaths($dataDir),
    8000,
);

$store->withUnlocked($userId, $dek, static function (PDO $pdo) use ($sleepMs, $label): void {
    fwrite(STDOUT, "locked\n");
    fflush(STDOUT);
    usleep((int) $sleepMs * 1000);
    $stmt = $pdo->prepare(
        'INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default)
         VALUES (?, ?, 0.5, 50, 0)'
    );
    $stmt->execute([bin2hex(random_bytes(8)), $label]);
});

fwrite(STDOUT, "ok\n");
fflush(STDOUT);
exit(0);
