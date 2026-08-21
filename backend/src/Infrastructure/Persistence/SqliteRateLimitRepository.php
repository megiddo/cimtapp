<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\RateLimitRepository;
use PDO;

final class SqliteRateLimitRepository implements RateLimitRepository
{
    public function __construct(private readonly GlobalConnection $global)
    {
    }

    public function purgeBefore(string $cutoffInclusive): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM rate_limit_hits WHERE hit_at <= :cutoff');
        $stmt->execute([':cutoff' => $cutoffInclusive]);
    }

    public function count(string $bucket): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM rate_limit_hits WHERE bucket = :bucket'
        );
        $stmt->execute([':bucket' => $bucket]);

        return (int) $stmt->fetchColumn();
    }

    public function hit(string $bucket, string $at): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO rate_limit_hits (bucket, hit_at) VALUES (:bucket, :at)'
        );
        $stmt->execute([
            ':bucket' => $bucket,
            ':at' => $at,
        ]);
    }

    private function pdo(): PDO
    {
        return $this->global->pdo();
    }
}
