<?php

declare(strict_types=1);

namespace App\Application\Settings;

final class EnvMerger
{
    /**
     * Later non-empty sources win so an empty Apache/Docker placeholder cannot
     * mask Google credentials loaded from `.env`.
     *
     * @param array<string, mixed> $server
     * @param array<string, mixed> $env
     * @param array<string, mixed> $fromGetenv
     * @return array<string, mixed>
     */
    public function merge(array $server, array $env, array $fromGetenv): array
    {
        $merged = $server;
        foreach ([$env, $fromGetenv] as $source) {
            foreach ($source as $key => $value) {
                if ($value === '' || $value === false || $value === null) {
                    continue;
                }
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
