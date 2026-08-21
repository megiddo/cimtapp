<?php

declare(strict_types=1);

use App\Application\Boot\BootServices;
use App\Application\Settings\SettingsInterface;
use App\Domain\Auth\AuthRateLimiter;
use App\Domain\Auth\AuthService;
use App\Domain\Auth\Clock;
use App\Domain\Auth\CredentialParser;
use App\Domain\Auth\EmailNormalizer;
use App\Domain\Auth\GoogleOAuthClient;
use App\Domain\Auth\IdGenerator;
use App\Domain\Auth\OauthStateService;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\SystemClock;
use App\Domain\Auth\UserProvisioner;
use App\Domain\Auth\UserStorePort;
use App\Domain\Crypto\AmkRotator;
use App\Domain\Crypto\Crypto;
use App\Domain\Dose\BacBottleService;
use App\Domain\Dose\CompoundService;
use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\PeptideCatalog;
use App\Domain\Dose\UserPeptideService;
use App\Domain\Dose\SyringeService;
use App\Domain\Dose\UseService;
use App\Infrastructure\Http\FileGetContentsHttpTransport;
use App\Infrastructure\Http\HttpGoogleOAuthClient;
use App\Infrastructure\Http\HttpTransport;
use App\Infrastructure\Http\SessionCookie;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalConnection;
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
        UserStorePort::class => static fn (ContainerInterface $c): UserStorePort => $c->get(UserStore::class),
        BootServices::class => static function (ContainerInterface $c): BootServices {
            return new BootServices($c->get(GlobalMigrator::class));
        },
        GlobalConnection::class => static function (ContainerInterface $c): GlobalConnection {
            return new GlobalConnection($c->get(DataPaths::class));
        },
        Clock::class => static fn (): Clock => new SystemClock(),
        IdGenerator::class => static fn (): IdGenerator => new IdGenerator(),
        EmailNormalizer::class => static fn (): EmailNormalizer => new EmailNormalizer(),
        CredentialParser::class => static fn (): CredentialParser => new CredentialParser(),
        DoseCalculator::class => static fn (): DoseCalculator => new DoseCalculator(),
        SyringeService::class => static function (ContainerInterface $c): SyringeService {
            return new SyringeService($c->get(IdGenerator::class));
        },
        BacBottleService::class => static function (ContainerInterface $c): BacBottleService {
            return new BacBottleService(
                $c->get(DoseCalculator::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        UserPeptideService::class => static function (ContainerInterface $c): UserPeptideService {
            return new UserPeptideService(
                $c->get(PeptideCatalog::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        CompoundService::class => static function (ContainerInterface $c): CompoundService {
            return new CompoundService(
                $c->get(DoseCalculator::class),
                $c->get(UserPeptideService::class),
                $c->get(SyringeService::class),
                $c->get(BacBottleService::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        UseService::class => static function (ContainerInterface $c): UseService {
            return new UseService(
                $c->get(DoseCalculator::class),
                $c->get(CompoundService::class),
                $c->get(SyringeService::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        PasswordHasher::class => static function (ContainerInterface $c): PasswordHasher {
            $settings = $c->get(SettingsInterface::class);

            return new PasswordHasher($settings->get('appEnv') !== 'testing');
        },
        SessionCookie::class => static function (ContainerInterface $c): SessionCookie {
            $settings = $c->get(SettingsInterface::class);

            return new SessionCookie((bool) $settings->get('sessionSecure'));
        },
        HttpTransport::class => static fn (): HttpTransport => new FileGetContentsHttpTransport(),
        GoogleOAuthClient::class => static function (ContainerInterface $c): GoogleOAuthClient {
            $settings = $c->get(SettingsInterface::class);

            return new HttpGoogleOAuthClient(
                (string) $settings->get('googleClientId'),
                (string) $settings->get('googleClientSecret'),
                (string) $settings->get('googleRedirectUri'),
                $c->get(HttpTransport::class),
            );
        },
        UserProvisioner::class => static function (ContainerInterface $c): UserProvisioner {
            return new UserProvisioner(
                $c->get(UserStorePort::class),
                $c->get(IdGenerator::class),
            );
        },
        AuthService::class => static function (ContainerInterface $c): AuthService {
            return new AuthService(
                $c->get(\App\Domain\Auth\UserRepository::class),
                $c->get(UserProvisioner::class),
                $c->get(Crypto::class),
                $c->get(PasswordHasher::class),
                $c->get(EmailNormalizer::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
                $c->get(UserStorePort::class),
            );
        },
        SessionService::class => static function (ContainerInterface $c): SessionService {
            return new SessionService(
                $c->get(\App\Domain\Auth\SessionRepository::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        OauthStateService::class => static function (ContainerInterface $c): OauthStateService {
            return new OauthStateService(
                $c->get(\App\Domain\Auth\OauthStateRepository::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        AuthRateLimiter::class => static function (ContainerInterface $c): AuthRateLimiter {
            return new AuthRateLimiter(
                $c->get(\App\Domain\Auth\RateLimitRepository::class),
                $c->get(Clock::class),
                $c->get(EmailNormalizer::class),
            );
        },
        AmkRotator::class => static function (ContainerInterface $c): AmkRotator {
            return new AmkRotator($c->get(\App\Domain\Auth\UserRepository::class));
        },
        \App\Application\Actions\Auth\GoogleCallbackAction::class => static function (ContainerInterface $c): \App\Application\Actions\Auth\GoogleCallbackAction {
            $settings = $c->get(SettingsInterface::class);

            return new \App\Application\Actions\Auth\GoogleCallbackAction(
                $c->get(LoggerInterface::class),
                $c->get(GoogleOAuthClient::class),
                $c->get(OauthStateService::class),
                $c->get(AuthService::class),
                $c->get(SessionService::class),
                $c->get(SessionCookie::class),
                (string) $settings->get('appUrl'),
            );
        },
    ]);
};
