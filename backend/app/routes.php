<?php

declare(strict_types=1);

use App\Application\Actions\Auth\GoogleCallbackAction;
use App\Application\Actions\Auth\GoogleStartAction;
use App\Application\Actions\Auth\LoginAction;
use App\Application\Actions\Auth\LogoutAction;
use App\Application\Actions\Auth\RegisterAction;
use App\Application\Actions\Health\HealthAction;
use App\Application\Actions\Me\SetPasswordAction;
use App\Application\Actions\Me\ViewMeAction;
use App\Application\Middleware\SessionAuthMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app): void {
    $app->group('/api/v1', function (Group $group): void {
        $group->get('/health', HealthAction::class);
        $group->post('/auth/register', RegisterAction::class);
        $group->post('/auth/login', LoginAction::class);
        $group->post('/auth/logout', LogoutAction::class);
        $group->get('/auth/google/start', GoogleStartAction::class);
        $group->get('/auth/google/callback', GoogleCallbackAction::class);

        $group->group('', function (Group $authed): void {
            $authed->get('/me', ViewMeAction::class);
            $authed->post('/me/password', SetPasswordAction::class);
        })->add(SessionAuthMiddleware::class);
    });
};
