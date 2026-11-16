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
use App\Domain\Auth\SessionIssuer;
use App\Domain\Auth\SessionService;
use App\Domain\Auth\SystemClock;
use App\Domain\Auth\UserProvisioner;
use App\Domain\Auth\UserStorePort;
use App\Domain\Crypto\AmkRotator;
use App\Domain\Crypto\Crypto;
use App\Domain\Dose\DoseCalculator;
use App\Domain\LogBook\AccountSyringeService;
use App\Domain\LogBook\LogEntryService;
use App\Domain\LogBook\LogPeptideService;
use App\Domain\LogBook\PersonService;
use App\Domain\LogBook\VialAssignmentService;
use App\Domain\LogBook\VialNamer;
use App\Domain\LogBook\VialService;
use App\Infrastructure\Http\SessionCookie;
use App\Infrastructure\OAuth\LeagueGoogleOAuthClient;
use App\Infrastructure\Persistence\DataPaths;
use App\Infrastructure\Persistence\GlobalConnection;
use App\Infrastructure\Persistence\GlobalMigrator;
use App\Infrastructure\Persistence\SqliteFileMigrator;
use App\Infrastructure\Persistence\UserMigrator;
use App\Infrastructure\Persistence\UserSchema\UserSchemaCatalog;
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
        UserSchemaCatalog::class => static function (ContainerInterface $c): UserSchemaCatalog {
            return UserSchemaCatalog::default(null, $c->get(LoggerInterface::class));
        },
        UserMigrator::class => static function (ContainerInterface $c): UserMigrator {
            return new UserMigrator($c->get(UserSchemaCatalog::class));
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
        VialNamer::class => static fn (): VialNamer => new VialNamer(),
        VialAssignmentService::class => static function (ContainerInterface $c): VialAssignmentService {
            return new VialAssignmentService(
                new \App\Domain\LogBook\VialRepository(),
                new \App\Domain\LogBook\VialPeopleRepository(),
                $c->get(VialNamer::class),
                $c->get(Clock::class),
            );
        },
        PersonService::class => static function (ContainerInterface $c): PersonService {
            return new PersonService(
                $c->get(VialAssignmentService::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
            );
        },
        LogPeptideService::class => static function (ContainerInterface $c): LogPeptideService {
            return new LogPeptideService(
                new \App\Domain\LogBook\LogPeptideRepository(),
                $c->get(IdGenerator::class),
            );
        },
        AccountSyringeService::class => static fn (): AccountSyringeService => new AccountSyringeService(
            new \App\Domain\LogBook\AccountSyringeRepository(),
        ),
        VialService::class => static function (ContainerInterface $c): VialService {
            return new VialService(
                new \App\Domain\LogBook\VialRepository(),
                new \App\Domain\LogBook\VialPeopleRepository(),
                $c->get(VialAssignmentService::class),
                new \App\Domain\LogBook\LidColorRepository(),
                $c->get(LogPeptideService::class),
                new \App\Domain\LogBook\PersonRepository(),
                $c->get(DoseCalculator::class),
                $c->get(VialNamer::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
                $c->get(LoggerInterface::class),
            );
        },
        LogEntryService::class => static function (ContainerInterface $c): LogEntryService {
            return new LogEntryService(
                new \App\Domain\LogBook\LogEntryRepository(),
                $c->get(PersonService::class),
                $c->get(VialService::class),
                new \App\Domain\LogBook\VialRepository(),
                new \App\Domain\LogBook\AccountSyringeRepository(),
                $c->get(DoseCalculator::class),
                $c->get(IdGenerator::class),
                $c->get(Clock::class),
                $c->get(LoggerInterface::class),
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
        GoogleOAuthClient::class => static function (ContainerInterface $c): GoogleOAuthClient {
            $settings = $c->get(SettingsInterface::class);

            return LeagueGoogleOAuthClient::fromCredentials(
                (string) $settings->get('googleClientId'),
                (string) $settings->get('googleClientSecret'),
                (string) $settings->get('googleRedirectUri'),
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
        SessionIssuer::class => static function (ContainerInterface $c): SessionIssuer {
            return new SessionIssuer(
                $c->get(SessionService::class),
                $c->get(SessionCookie::class),
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
                $c->get(\App\Domain\Auth\SessionIssuer::class),
                (string) $settings->get('appUrl'),
            );
        },
    ]);
};
