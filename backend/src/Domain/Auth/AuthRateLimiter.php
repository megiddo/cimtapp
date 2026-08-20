<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeZone;

/**
 * Single-instance limiter: counters live in global sqlite. 10 hits / 15 minutes.
 */
final class AuthRateLimiter
{
    public function __construct(
        private readonly RateLimitRepository $hits,
        private readonly Clock $clock,
        private readonly EmailNormalizer $emails,
    ) {
    }

    public function guardLogin(string $ip, string $email): void
    {
        $buckets = [$this->bucket('login', 'ip', $ip)];
        $normalized = $this->emails->normalize($email);
        if ($normalized !== '') {
            $buckets[] = $this->bucket('login', 'email', $normalized);
        }

        $this->guard($buckets);
    }

    public function guardGoogleStart(string $ip): void
    {
        $this->guard([$this->bucket('google-start', 'ip', $ip)]);
    }

    /**
     * @param list<string> $buckets
     */
    private function guard(array $buckets): void
    {
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $cutoff = $now->modify('-' . AuthConfig::RATE_LIMIT_WINDOW_SECONDS . ' seconds')
            ->format('Y-m-d\TH:i:s\Z');
        $this->hits->purgeBefore($cutoff);

        foreach ($buckets as $bucket) {
            if ($this->hits->count($bucket) >= AuthConfig::RATE_LIMIT_MAX) {
                throw new RateLimitException(AuthConfig::RATE_LIMIT_MESSAGE);
            }
        }

        $at = $now->format('Y-m-d\TH:i:s\Z');
        foreach ($buckets as $bucket) {
            $this->hits->hit($bucket, $at);
        }
    }

    private function bucket(string $action, string $kind, string $value): string
    {
        return $action . ':' . $kind . ':' . $value;
    }
}
