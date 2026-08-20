<?php

declare(strict_types=1);

use App\Application\Actions\Auth\GoogleCallbackAction;
use App\Application\Actions\Auth\GoogleStartAction;
use App\Application\Actions\Auth\LoginAction;
use App\Application\Actions\Auth\LogoutAction;
use App\Application\Actions\Auth\RegisterAction;
use App\Application\Actions\Catalog\ListPeptideTypesAction;
use App\Application\Actions\Compounds\CreateCompoundAction;
use App\Application\Actions\Compounds\CurrentCompoundAction;
use App\Application\Actions\Compounds\ListCompoundsAction;
use App\Application\Actions\Compounds\PatchCompoundAction;
use App\Application\Actions\Compounds\ViewCompoundAction;
use App\Application\Actions\Health\HealthAction;
use App\Application\Actions\Me\ExportUserAction;
use App\Application\Actions\Me\SetPasswordAction;
use App\Application\Actions\Me\ViewMeAction;
use App\Application\Actions\Syringes\CreateSyringeAction;
use App\Application\Actions\Syringes\ListSyringesAction;
use App\Application\Actions\Syringes\PatchSyringeAction;
use App\Application\Actions\Uses\CreateUseAction;
use App\Application\Actions\Uses\ListUsesAction;
use App\Application\Actions\Uses\PatchUseAction;
use App\Application\Actions\Uses\ViewUseAction;
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
            $authed->get('/me/export', ExportUserAction::class);
            $authed->get('/peptide-types', ListPeptideTypesAction::class);
            $authed->get('/syringes', ListSyringesAction::class);
            $authed->post('/syringes', CreateSyringeAction::class);
            $authed->patch('/syringes/{id}', PatchSyringeAction::class);
            $authed->get('/compounds', ListCompoundsAction::class);
            $authed->post('/compounds', CreateCompoundAction::class);
            $authed->get('/compounds/current', CurrentCompoundAction::class);
            $authed->get('/compounds/{id}', ViewCompoundAction::class);
            $authed->patch('/compounds/{id}', PatchCompoundAction::class);
            $authed->get('/uses', ListUsesAction::class);
            $authed->post('/uses', CreateUseAction::class);
            $authed->get('/uses/{id}', ViewUseAction::class);
            $authed->patch('/uses/{id}', PatchUseAction::class);
        })->add(SessionAuthMiddleware::class);
    });
};
