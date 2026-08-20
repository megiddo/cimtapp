<?php

declare(strict_types=1);

namespace App\Application\Boot;

use App\Infrastructure\Persistence\GlobalMigrator;

/**
 * Side effects that run once per Slim process boot.
 */
final class BootServices
{
    public function __construct(private readonly GlobalMigrator $globalMigrator)
    {
    }

    public function boot(): int
    {
        return $this->globalMigrator->migrate();
    }
}
