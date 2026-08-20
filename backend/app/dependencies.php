<?php

declare(strict_types=1);

use App\Application\Boot\BootServices;
use App\Application\Settings\SettingsInterface;
use App\Domain\Crypto\Crypto;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\SqliteFileMigrator;
use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserStore;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
            $settings = $c->get(SettingsInterface::class);

            $loggerSettings = $settings->get('logger');
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        DataPaths::class => static function (ContainerInterface $c): DataPaths {
            $settings = $c->get(SettingsInterface::class);

            return new DataPaths((string) $settings->get('dataDir'));
        },
        Crypto::class => static function (ContainerInterface $c): Crypto {
            $settings = $c->get(SettingsInterface::class);

            return Crypto::fromMasterKey((string) $settings->get('masterKey'));
        },
        SqliteFileMigrator::class => static fn (): SqliteFileMigrator => new SqliteFileMigrator(),
        GlobalMigrator::class => static function (ContainerInterface $c): GlobalMigrator {
            return new GlobalMigrator(
                $c->get(DataPaths::class),
                dirname(__DIR__) . '/migrations/global',
                $c->get(SqliteFileMigrator::class),
            );
        },
        UserMigrator::class => static function (ContainerInterface $c): UserMigrator {
            return new UserMigrator(
                dirname(__DIR__) . '/migrations/user',
                $c->get(SqliteFileMigrator::class),
            );
        },
        UserStore::class => static function (ContainerInterface $c): UserStore {
            return new UserStore(
                $c->get(Crypto::class),
                $c->get(UserMigrator::class),
                $c->get(DataPaths::class),
            );
        },
        BootServices::class => static function (ContainerInterface $c): BootServices {
            return new BootServices($c->get(GlobalMigrator::class));
        },
    ]);
};
