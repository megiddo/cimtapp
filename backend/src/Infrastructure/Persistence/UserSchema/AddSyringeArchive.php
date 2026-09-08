<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use App\Domain\Dose\DoseCalculator;
use PDO;

final class AddSyringeArchive extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V7SyringeArchive;
    }

    protected function filename(): string
    {
        return '007_syringe_archive.sql';
    }

    public function apply(PDO $pdo): void
    {
        parent::apply($pdo);
        $this->backfillClosedVials($pdo);
    }

    private function backfillClosedVials(PDO $pdo): void
    {
        $doses = new DoseCalculator();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $pdo->query(
            'SELECT id, peptide_mg FROM compounds WHERE is_open = 0 AND archived_at IS NULL'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usedStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(peptide_mg), 0) FROM uses WHERE compound_id = :id'
        );
        $adjStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(delta_mg), 0) FROM compound_adjustments WHERE compound_id = :id'
        );
        $archive = $pdo->prepare(
            'UPDATE compounds SET archived_at = :archived_at WHERE id = :id'
        );
        $reopen = $pdo->prepare(
            'UPDATE compounds SET is_open = 1 WHERE id = :id'
        );

        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $usedStmt->execute([':id' => $id]);
            $adjStmt->execute([':id' => $id]);
            $remaining = (float) $row['peptide_mg']
                - (float) $usedStmt->fetchColumn()
                + (float) $adjStmt->fetchColumn();
            if ($doses->isDepleted($remaining)) {
                $archive->execute([':archived_at' => $now, ':id' => $id]);
            } else {
                $reopen->execute([':id' => $id]);
            }
        }
    }
}
