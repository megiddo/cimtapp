<?php

declare(strict_types=1);

namespace App\Domain\Health;

use InvalidArgumentException;

/**
 * Liveness payload for GET /api/v1/health.
 */
final class HealthStatus
{
    public const OK = 'ok';
    public const DEGRADED = 'degraded';

    private function __construct(private readonly string $status)
    {
        if ($status !== self::OK && $status !== self::DEGRADED) {
            throw new InvalidArgumentException('Invalid health status: ' . $status);
        }
    }

    public static function ok(): self
    {
        return new self(self::OK);
    }

    public static function degraded(): self
    {
        return new self(self::DEGRADED);
    }

    public static function fromString(string $status): self
    {
        return new self($status);
    }

    public function isOk(): bool
    {
        return $this->status === self::OK;
    }

    public function isDegraded(): bool
    {
        return $this->status === self::DEGRADED;
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array{status: string}
     */
    public function toArray(): array
    {
        return ['status' => $this->status];
    }

    public function equals(self $other): bool
    {
        return $this->status === $other->status;
    }
}
