<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use App\Domain\Auth\IdGenerator;
use App\Domain\Dose\DoseConfig;
use PDO;

final class AddProfiles extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V6Profiles;
    }

    protected function filename(): string
    {
        return '006_profiles.sql';
    }

    public function apply(PDO $pdo): void
    {
        parent::apply($pdo);
        $this->seedDefault($pdo);
    }

    private function seedDefault(PDO $pdo): void
    {
        $stmt = $pdo->query('SELECT id FROM profiles WHERE is_default = 1 LIMIT 1');
        $existing = $stmt === false ? false : $stmt->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            $id = $existing;
        } else {
            $id = (new IdGenerator())->uuid();
            $insert = $pdo->prepare(
                'INSERT INTO profiles (id, name, is_default, created_at)
                 VALUES (:id, :name, 1, :created_at)'
            );
            $insert->execute([
                ':id' => $id,
                ':name' => DoseConfig::PROFILE_DEFAULT_NAME,
                ':created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ]);
        }

        $updateUses = $pdo->prepare('UPDATE uses SET profile_id = :id WHERE profile_id IS NULL');
        $updateUses->execute([':id' => $id]);

        $link = $pdo->prepare(
            'INSERT OR IGNORE INTO compound_profiles (compound_id, profile_id)
             SELECT id, :id FROM compounds'
        );
        $link->execute([':id' => $id]);
    }
}
