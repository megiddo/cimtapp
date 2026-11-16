<?php

declare(strict_types=1);

use App\Application\Actions\Auth\GoogleCallbackAction;
use App\Application\Actions\Auth\GoogleStartAction;
use App\Application\Actions\Auth\LoginAction;
use App\Application\Actions\Auth\LogoutAction;
use App\Application\Actions\Auth\RegisterAction;
use App\Application\Actions\Health\HealthAction;
use App\Application\Actions\LogBook\GetAccountSyringeAction;
use App\Application\Actions\LogBook\Log\CreateLogAction;
use App\Application\Actions\LogBook\Log\DeleteLogAction;
use App\Application\Actions\LogBook\Log\ListLogAction;
use App\Application\Actions\LogBook\Log\LogDefaultsAction;
use App\Application\Actions\LogBook\Log\PatchLogAction;
use App\Application\Actions\LogBook\Log\ViewLogAction;
use App\Application\Actions\LogBook\People\CreatePersonAction;
use App\Application\Actions\LogBook\People\ListPeopleAction;
use App\Application\Actions\LogBook\People\ListRemovedPeopleAction;
use App\Application\Actions\LogBook\People\PatchPersonAction;
use App\Application\Actions\LogBook\People\RemovePersonAction;
use App\Application\Actions\LogBook\People\RestorePersonAction;
use App\Application\Actions\LogBook\Peptides\CreatePeptideAction;
use App\Application\Actions\LogBook\Peptides\ListPeptidesAction;
use App\Application\Actions\LogBook\Peptides\RemovePeptideAction;
use App\Application\Actions\LogBook\PutAccountSyringeAction;
use App\Application\Actions\LogBook\Vials\CreateVialAction;
use App\Application\Actions\LogBook\Vials\ListVialsAction;
use App\Application\Actions\Me\ExportUserAction;
use App\Application\Actions\Me\RestorePreMigrationBackupAction;
use App\Application\Actions\Me\SetPasswordAction;
use App\Application\Actions\Me\ViewMeAction;
use App\Application\Actions\Me\ViewPreMigrationBackupAction;
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
            $authed->get('/me/store-backup', ViewPreMigrationBackupAction::class);
            $authed->post('/me/store-backup/restore', RestorePreMigrationBackupAction::class);
            $authed->get('/people', ListPeopleAction::class);
            $authed->get('/people/removed', ListRemovedPeopleAction::class);
            $authed->post('/people', CreatePersonAction::class);
            $authed->patch('/people/{id}', PatchPersonAction::class);
            $authed->post('/people/{id}/remove', RemovePersonAction::class);
            $authed->post('/people/{id}/restore', RestorePersonAction::class);
            $authed->get('/peptides', ListPeptidesAction::class);
            $authed->post('/peptides', CreatePeptideAction::class);
            $authed->post('/peptides/{id}/remove', RemovePeptideAction::class);
            $authed->get('/syringe', GetAccountSyringeAction::class);
            $authed->put('/syringe', PutAccountSyringeAction::class);
            $authed->get('/vials', ListVialsAction::class);
            $authed->post('/vials', CreateVialAction::class);
            $authed->get('/log/defaults', LogDefaultsAction::class);
            $authed->get('/log', ListLogAction::class);
            $authed->post('/log', CreateLogAction::class);
            $authed->get('/log/{id}', ViewLogAction::class);
            $authed->patch('/log/{id}', PatchLogAction::class);
            $authed->delete('/log/{id}', DeleteLogAction::class);
        })->add(SessionAuthMiddleware::class);
    });
};