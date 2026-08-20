<?php

declare(strict_types=1);

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder): void {
    // Encrypted per-user SQLite repositories land in Phase 1.
    $containerBuilder->addDefinitions([]);
};
