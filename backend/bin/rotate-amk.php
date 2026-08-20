<?php

declare(strict_types=1);

use App\Application\Boot\BootServices;
use App\Application\Settings\SettingsInterface;
use App\Domain\Crypto\AmkRotator;
use App\Domain\Crypto\Crypto;
use App\Infrastructure\Persistence\UserStore;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Re-wrap every users.encrypted_dek with a new AMK.
 *
 * Usage: php bin/rotate-amk.php <new-master-key>
 *
 * Current AMK is CIMT_MASTER_KEY. After success, set CIMT_MASTER_KEY to the
 * new key before serving traffic. .enc user files are not rewritten.
 */
$backendEnv = __DIR__ . '/../.env';
if (is_file($backendEnv)) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->safeLoad();
}
$rootEnv = dirname(__DIR__, 2) . '/.env';
if (is_file($rootEnv)) {
    Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
}

$newKey = $argv[1] ?? '';
if ($newKey === '' || $newKey === '-h' || $newKey === '--help') {
    fwrite(STDERR, "Usage: php bin/rotate-amk.php <new-master-key>\n");
    fwrite(STDERR, "Reads the current AMK from CIMT_MASTER_KEY.\n");
    exit(1);
}

$containerBuilder = new ContainerBuilder();
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);
$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);
$container = $containerBuilder->build();
$container->get(BootServices::class)->boot();

$from = $container->get(Crypto::class);
$to = Crypto::fromMasterKey($newKey);
$rotated = $container->get(AmkRotator::class)->rotate($from, $to);

$users = $container->get(\App\Domain\Auth\UserRepository::class)->listAll();
$store = $container->get(UserStore::class);
foreach ($users as $user) {
    $dek = $to->unwrapDek($user->dekNonce, $user->encryptedDek);
    $bytes = $store->exportPlaintext($user->id, $dek);
    if (!str_starts_with($bytes, 'SQLite format 3')) {
        fwrite(STDERR, "Verify failed for user {$user->id}\n");
        exit(1);
    }
}

$settingsValues = $container->get(SettingsInterface::class);
fwrite(STDOUT, 'Rewrapped ' . $rotated . ' DEK(s). Set CIMT_MASTER_KEY to the new key before restart. DATA_DIR=' . $settingsValues->get('dataDir') . "\n");
exit(0);
