<?php

declare(strict_types=1);

namespace Tests\Application\Boot;

use App\Application\Boot\BootServices;
use App\Application\Settings\SettingsInterface;
use App\Domain\Crypto\Crypto;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\UserStore;
use PDO;
use Tests\TestCase;

class BootServicesTest extends TestCase
{
    public function testBootAppliesThenNoOps(): void
    {
        $dir = $this->makeTempDir('cimtapp-boot-');
        try {
            $boot = new BootServices(
                new GlobalMigrator(
                    new DataPaths($dir),
                    dirname(__DIR__, 3) . '/migrations/global',
                )
            );
            $this->assertSame(2, $boot->boot());
            $this->assertSame(0, $boot->boot());
            $this->assertFileExists($dir . '/global.sqlite');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testAppInstanceMigratesAndResolvesCrypto(): void
    {
        $app = $this->getAppInstance();
        $container = $app->getContainer();
        $this->assertNotNull($container);

        $settings = $container->get(SettingsInterface::class);
        $dataDir = (string) $settings->get('dataDir');
        $this->assertFileExists($dataDir . '/global.sqlite');

        $pdo = new PDO('sqlite:' . $dataDir . '/global.sqlite');
        $slugs = $pdo->query('SELECT slug FROM peptide_types ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(
            ['semaglutide', 'tirzepatide', 'retatrutide', 'liraglutide'],
            $slugs
        );

        $crypto = $container->get(Crypto::class);
        $this->assertInstanceOf(Crypto::class, $crypto);
        $store = $container->get(UserStore::class);
        $this->assertInstanceOf(UserStore::class, $store);
        $this->assertTrue((bool) $settings->get('masterKeyConfigured'));
        $this->assertNotSame('', (string) $settings->get('masterKey'));
    }
}
