<?php

declare(strict_types=1);

use Slim\App;

return function (App $app): void {
    // Cookie sessions, auth middleware, and user-DB unlock land in Phase 1.
    // Same-origin SPA: no CORS middleware.
};
