<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use PDO;

final class CompoundProfileLinker
{
    public function __construct(private readonly ProfilePolicy $policy)
    {
    }

    public function compoundLinked(PDO $pdo, string $compoundId, string $profileId): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM compound_profiles
             WHERE compound_id = :compound_id AND profile_id = :profile_id
             LIMIT 1'
        );
        $stmt->execute([
            ':compound_id' => $compoundId,
            ':profile_id' => $profileId,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return list<string>
     */
    public function profileIdsForCompound(PDO $pdo, string $compoundId): array
    {
        $stmt = $pdo->prepare(
            'SELECT profile_id FROM compound_profiles WHERE compound_id = :id ORDER BY profile_id ASC'
        );
        $stmt->execute([':id' => $compoundId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map(static fn (mixed $id): string => (string) $id, is_array($rows) ? $rows : []));
    }

    /**
     * @param list<string> $profileIds
     */
    public function replaceCompoundProfiles(PDO $pdo, string $compoundId, array $profileIds): void
    {
        $ids = $this->policy->requireIds($pdo, $profileIds);
        $delete = $pdo->prepare('DELETE FROM compound_profiles WHERE compound_id = :id');
        $delete->execute([':id' => $compoundId]);
        $insert = $pdo->prepare(
            'INSERT INTO compound_profiles (compound_id, profile_id) VALUES (:compound_id, :profile_id)'
        );
        foreach ($ids as $profileId) {
            $insert->execute([
                ':compound_id' => $compoundId,
                ':profile_id' => $profileId,
            ]);
        }
    }
}
