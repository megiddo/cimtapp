<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\User;
use App\Domain\Auth\UserRepository;
use App\Domain\Crypto\WrappedDek;
use PDO;
use Throwable;

final class SqliteUserRepository implements UserRepository
{
    public function __construct(private readonly GlobalConnection $global)
    {
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function findByGoogleSub(string $sub): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE google_sub = :sub LIMIT 1');
        $stmt->execute([':sub' => $sub]);

        return $this->hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public function listAll(): array
    {
        $stmt = $this->pdo()->query(
            'SELECT * FROM users ORDER BY created_at ASC, id ASC'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        $users = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $user = $this->hydrate($row);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    public function insert(User $user): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (
                id, email, password_hash, google_sub, encrypted_dek, dek_nonce, created_at, last_login_at
             ) VALUES (
                :id, :email, :password_hash, :google_sub, :encrypted_dek, :dek_nonce, :created_at, :last_login_at
             )'
        );
        $stmt->execute([
            ':id' => $user->id,
            ':email' => $user->email,
            ':password_hash' => $user->passwordHash,
            ':google_sub' => $user->googleSub,
            ':encrypted_dek' => $user->encryptedDek,
            ':dek_nonce' => $user->dekNonce,
            ':created_at' => $user->createdAt,
            ':last_login_at' => $user->lastLoginAt,
        ]);
    }

    public function transactional(callable $callback): void
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $callback();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function setGoogleSub(string $userId, string $googleSub): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET google_sub = :sub WHERE id = :id');
        $stmt->execute([':sub' => $googleSub, ':id' => $userId]);
    }

    public function setPasswordHash(string $userId, string $passwordHash): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $passwordHash, ':id' => $userId]);
    }

    public function updateLastLogin(string $userId, string $lastLoginAt): void
    {
        $stmt = $this->pdo()->prepare('UPDATE users SET last_login_at = :at WHERE id = :id');
        $stmt->execute([':at' => $lastLoginAt, ':id' => $userId]);
    }

    public function updateWrappedDek(string $userId, WrappedDek $wrapped): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE users SET encrypted_dek = :ciphertext, dek_nonce = :nonce WHERE id = :id'
        );
        $stmt->execute([
            ':ciphertext' => $wrapped->ciphertext(),
            ':nonce' => $wrapped->nonce(),
            ':id' => $userId,
        ]);
    }

    private function pdo(): PDO
    {
        return $this->global->pdo();
    }

    /**
     * @param array<string, mixed>|false $row
     */
    private function hydrate(array|false $row): ?User
    {
        if ($row === false) {
            return null;
        }

        $passwordHash = $row['password_hash'] ?? null;
        $googleSub = $row['google_sub'] ?? null;
        $lastLogin = $row['last_login_at'] ?? null;

        return new User(
            (string) $row['id'],
            (string) $row['email'],
            is_string($passwordHash) ? $passwordHash : null,
            is_string($googleSub) ? $googleSub : null,
            (string) $row['encrypted_dek'],
            (string) $row['dek_nonce'],
            (string) $row['created_at'],
            is_string($lastLogin) ? $lastLogin : null,
        );
    }
}
