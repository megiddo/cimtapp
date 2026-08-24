<?php

declare(strict_types=1);

use App\Application\Settings\EnvValidator;
use App\Application\Settings\Settings;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Monolog\Level;

return function (ContainerBuilder $containerBuilder): void {
    $validator = new EnvValidator();
    $fromGetenv = [];
    foreach (
        [
            'APP_ENV',
            'CIMT_MASTER_KEY',
            'DATA_DIR',
            'APP_URL',
            'SESSION_SECURE',
            'GOOGLE_CLIENT_ID',
            'GOOGLE_CLIENT_SECRET',
            'GOOGLE_REDIRECT_URI',
        ] as $key
    ) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            $fromGetenv[$key] = $value;
        }
    }
    $env = $validator->mergeProcessEnv($_SERVER, $_ENV, $fromGetenv);
    $validated = $validator->validate($env);

    $containerBuilder->addDefinitions([
        SettingsInterface::class => static function () use ($validated): SettingsInterface {
            $isProd = $validated['appEnv'] === 'production';

            return new Settings([
                'displayErrorDetails' => !$isProd,
                'logError' => true,
                'logErrorDetails' => !$isProd,
                'appEnv' => $validated['appEnv'],
                'dataDir' => $validated['dataDir'],
                'appUrl' => $validated['appUrl'],
                'sessionSecure' => $validated['sessionSecure'],
                'masterKey' => $validated['masterKey'],
                'masterKeyConfigured' => true,
                'googleClientId' => $validated['googleClientId'],
                'googleClientSecret' => $validated['googleClientSecret'],
                'googleRedirectUri' => $validated['googleRedirectUri'],
                'logger' => [
                    'name' => 'cimtapp',
                    'path' => isset($_ENV['docker']) || getenv('docker') ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                    'level' => Level::Debug,
                ],
            ]);
        },
    ]);
};
