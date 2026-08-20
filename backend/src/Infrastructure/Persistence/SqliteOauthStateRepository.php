<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\OauthState;
use App\Domain\Auth\OauthStateRepository;
use PDO;

final class SqliteOauthStateRepository implements OauthStateRepository
{
    public function __construct(private readonly GlobalConnection $global)
    {
    }

    public function insert(OauthState $state): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO oauth_states (state, expires_at, redirect_after, code_verifier)
             VALUES (:state, :expires_at, :redirect_after, :code_verifier)'
        );
        $stmt->execute([
            ':state' => $state->state,
            ':expires_at' => $state->expiresAt,
            ':redirect_after' => $state->redirectAfter,
            ':code_verifier' => $state->codeVerifier,
        ]);
    }

    public function findByState(string $state): ?OauthState
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM oauth_states WHERE state = :state LIMIT 1');
        $stmt->execute([':state' => $state]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $redirect = $row['redirect_after'] ?? null;
        $verifier = $row['code_verifier'] ?? '';

        return new OauthState(
            (string) $row['state'],
            (string) $row['expires_at'],
            is_string($redirect) ? $redirect : null,
            is_string($verifier) ? $verifier : '',
        );
    }

    public function delete(string $state): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM oauth_states WHERE state = :state');
        $stmt->execute([':state' => $state]);
    }

    private function pdo(): PDO
    {
        return $this->global->pdo();
    }
}
