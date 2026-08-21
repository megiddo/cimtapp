<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use PDO;

final class UserProvisioner
{
    public function __construct(
        private readonly UserStorePort $store,
        private readonly IdGenerator $ids,
    ) {
    }

    public function provision(
        string $userId,
        string $dek,
        string $email,
        ?string $passwordHash,
        ?string $googleSub,
        string $now,
    ): void {
        $this->store->create($userId, $dek);
        $this->store->withUnlocked($userId, $dek, function (PDO $pdo) use (
            $userId,
            $email,
            $passwordHash,
            $googleSub,
            $now,
        ): void {
            $this->insertAccount($pdo, $userId, $email, $passwordHash, $googleSub, $now);
            $this->insertDefaultSyringe($pdo);
        });
    }

    public function syncAccount(
        PDO $pdo,
        string $userId,
        string $email,
        ?string $passwordHash,
        ?string $googleSub,
        string $now,
    ): void {
        $stmt = $pdo->prepare(
            'UPDATE account
             SET email = :email,
                 password_hash = :password_hash,
                 google_sub = :google_sub,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':google_sub' => $googleSub,
            ':updated_at' => $now,
            ':user_id' => $userId,
        ]);
    }

    private function insertAccount(
        PDO $pdo,
        string $userId,
        string $email,
        ?string $passwordHash,
        ?string $googleSub,
        string $now,
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO account (user_id, email, password_hash, google_sub, updated_at)
             VALUES (:user_id, :email, :password_hash, :google_sub, :updated_at)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':password_hash' => $passwordHash,
            ':google_sub' => $googleSub,
            ':updated_at' => $now,
        ]);
    }

    private function insertDefaultSyringe(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO syringe_profiles (id, label, volume_ml, capacity_iu, is_default)
             VALUES (:id, :label, :volume_ml, :capacity_iu, 1)'
        );
        $stmt->execute([
            ':id' => $this->ids->uuid(),
            ':label' => AuthConfig::DEFAULT_SYRINGE_LABEL,
            ':volume_ml' => AuthConfig::DEFAULT_SYRINGE_VOLUME_ML,
            ':capacity_iu' => AuthConfig::DEFAULT_SYRINGE_CAPACITY_IU,
        ]);
    }
}
