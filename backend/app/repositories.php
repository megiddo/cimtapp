<?php

declare(strict_types=1);

use App\Domain\Auth\OauthStateRepository;
use App\Domain\Auth\SessionRepository;
use App\Domain\Auth\UserRepository;
use App\Infrastructure\Persistence\SqliteOauthStateRepository;
use App\Infrastructure\Persistence\SqliteSessionRepository;
use App\Infrastructure\Persistence\SqliteUserRepository;
use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    $containerBuilder->addDefinitions([
        UserRepository::class => static fn (\Psr\Container\ContainerInterface $c): UserRepository => $c->get(SqliteUserRepository::class),
        SessionRepository::class => static fn (\Psr\Container\ContainerInterface $c): SessionRepository => $c->get(SqliteSessionRepository::class),
        OauthStateRepository::class => static fn (\Psr\Container\ContainerInterface $c): OauthStateRepository => $c->get(SqliteOauthStateRepository::class),
    ]);
};
