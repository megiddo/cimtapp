<?php

declare(strict_types=1);

use App\Application\Actions\Health\HealthAction;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app): void {
    $app->group('/api/v1', function (Group $group): void {
        $group->get('/health', HealthAction::class);
        // Auth, compounds, uses, syringes: later milestones.
    });
};
