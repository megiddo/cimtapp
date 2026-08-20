<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\Session;
use App\Domain\Auth\SessionRepository;
use PDO;

final class SqliteSessionRepository implements SessionRepository
{
    public function __construct(private readonly GlobalConnection $global)
    {
    }

    public function insert(Session $session): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO sessions (id, user_id, expires_at, created_at)
             VALUES (:id, :user_id, :expires_at, :created_at)'
        );
        $stmt->execute([
            ':id' => $session->id,
            ':user_id' => $session->userId,
            ':expires_at' => $session->expiresAt,
            ':created_at' => $session->createdAt,
        ]);
    }

    public function findById(string $id): ?Session
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM sessions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return new Session(
            (string) $row['id'],
            (string) $row['user_id'],
            (string) $row['expires_at'],
            (string) $row['created_at'],
        );
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function updateExpiry(string $id, string $expiresAt): void
    {
        $stmt = $this->pdo()->prepare('UPDATE sessions SET expires_at = :expires_at WHERE id = :id');
        $stmt->execute([':expires_at' => $expiresAt, ':id' => $id]);
    }

    private function pdo(): PDO
    {
        return $this->global->pdo();
    }
}
