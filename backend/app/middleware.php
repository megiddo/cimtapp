<?php

declare(strict_types=1);

use Slim\App;

return function (App $app): void {
    // Cookie session auth lives on protected route groups (see routes.php).
    // Same-origin SPA: no CORS middleware.
};
