<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

use App\Domain\Dose\DoseCalculator;
use App\Domain\Dose\DoseConfig;
use App\Domain\LogBook\LogBookConfig;
use App\Domain\LogBook\AccountSyringeRepository;
use App\Domain\LogBook\VialNamer;
use PDO;

/**
 * v7 → v8 data migration inside {@see AddLogBook}.
 */
final class LogBookMigrationBackfill
{
    public function __construct(
        private readonly DoseCalculator $doses = new DoseCalculator(),
        private readonly VialNamer $namer = new VialNamer(),
    ) {
    }

    public function apply(PDO $pdo): void
    {
        $this->seedAccountSyringe($pdo);
        $this->migratePeptides($pdo);
        if (!$this->isFreshDefaultAccount($pdo)) {
            $this->migratePeople($pdo);
        }
        $this->migrateVials($pdo);
        $this->migrateVialPeople($pdo);
        $this->applyUsageOffsets($pdo);
        $this->migrateLogEntries($pdo);
        $this->resolveCurrentVials($pdo);
    }

    private function isFreshDefaultAccount(PDO $pdo): bool
    {
        $profiles = $pdo->query('SELECT id, name, is_default FROM profiles')->fetchAll(PDO::FETCH_ASSOC);
        if (count($profiles) !== 1) {
            return false;
        }
        $row = $profiles[0];
        if ((int) ($row['is_default'] ?? 0) !== 1) {
            return false;
        }
        if ((string) $row['name'] !== DoseConfig::PROFILE_DEFAULT_NAME) {
            return false;
        }
        $compounds = (int) $pdo->query('SELECT COUNT(*) FROM compounds')->fetchColumn();
        $uses = (int) $pdo->query('SELECT COUNT(*) FROM uses')->fetchColumn();

        return $compounds === 0 && $uses === 0;
    }

    private function migratePeople(PDO $pdo): void
    {
        $rows = $pdo->query('SELECT id, name, created_at FROM profiles ORDER BY created_at ASC')->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT INTO people (id, name, created_at, removed_at) VALUES (:id, :name, :created_at, NULL)'
        );
        foreach ($rows as $row) {
            $insert->execute([
                ':id' => (string) $row['id'],
                ':name' => (string) $row['name'],
                ':created_at' => (string) $row['created_at'],
            ]);
        }
    }

    private function migratePeptides(PDO $pdo): void
    {
        $this->ensurePeptide($pdo, LogBookConfig::SLUG_TIRZEPATIDE, LogBookConfig::NAME_TIRZEPATIDE);
        $this->ensurePeptide($pdo, LogBookConfig::SLUG_RETATRUTIDE, LogBookConfig::NAME_RETATRUTIDE);

        $custom = $pdo->query('SELECT id, slug, name, created_at FROM user_peptide_types')->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT OR IGNORE INTO peptides (id, name, slug, removed_at) VALUES (:id, :name, :slug, NULL)'
        );
        foreach ($custom as $row) {
            $insert->execute([
                ':id' => (string) $row['id'],
                ':name' => (string) $row['name'],
                ':slug' => (string) $row['slug'],
            ]);
        }

        $referenced = $this->referencedSlugs($pdo);
        foreach (['semaglutide', 'liraglutide'] as $slug) {
            if (!in_array($slug, $referenced, true)) {
                $pdo->prepare(
                    'UPDATE peptides SET removed_at = :now WHERE slug = :slug AND removed_at IS NULL'
                )->execute([':now' => gmdate('Y-m-d\TH:i:s\Z'), ':slug' => $slug]);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function referencedSlugs(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT DISTINCT peptide_type_slug FROM compounds');
        $slugs = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map(static fn ($s): string => (string) $s, $slugs));
    }

    private function ensurePeptide(PDO $pdo, string $slug, string $name): void
    {
        $pdo->prepare(
            'INSERT OR IGNORE INTO peptides (id, name, slug, removed_at) VALUES (:id, :name, :slug, NULL)'
        )->execute([':id' => $slug, ':name' => $name, ':slug' => $slug]);
        $pdo->prepare(
            'UPDATE peptides SET removed_at = NULL, name = :name WHERE slug = :slug'
        )->execute([':name' => $name, ':slug' => $slug]);
    }

    private function seedAccountSyringe(PDO $pdo): void
    {
        $row = $pdo->query(
            'SELECT volume_ml, capacity_iu FROM syringe_profiles WHERE is_default = 1 ORDER BY rowid LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            $row = [
                'volume_ml' => DoseConfig::FALLBACK_SYRINGE_VOLUME_ML,
                'capacity_iu' => DoseConfig::FALLBACK_SYRINGE_CAPACITY_IU,
            ];
        }
        $pdo->prepare(
            'INSERT INTO account_syringe (id, volume_ml, capacity_iu) VALUES (1, :vol, :cap)'
        )->execute([
            ':vol' => (float) $row['volume_ml'],
            ':cap' => (float) $row['capacity_iu'],
        ]);
    }

    private function migrateVials(PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT id, peptide_type_id, peptide_type_name, peptide_mg, bac_water_ml, compounded_at, created_at, name, archived_at, is_open
             FROM compounds'
        )->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT INTO vials (
                id, peptide_id, peptide_name, peptide_mg, bac_water_ml, concentration_mg_per_ml,
                display_name, lid_color_id, lid_color_name, usage_offset_mg, compounded_at, superseded_at, created_at
            ) VALUES (
                :id, :peptide_id, :peptide_name, :peptide_mg, :bac_water_ml, :concentration,
                :display_name, NULL, \'\', 0, :compounded_at, NULL, :created_at
            )'
        );
        foreach ($rows as $row) {
            $conc = $this->doses->concentration((float) $row['peptide_mg'], (float) $row['bac_water_ml']);
            $peptideId = $this->resolvePeptideId($pdo, (string) $row['peptide_type_id']);
            $insert->execute([
                ':id' => (string) $row['id'],
                ':peptide_id' => $peptideId,
                ':peptide_name' => (string) $row['peptide_type_name'],
                ':peptide_mg' => (float) $row['peptide_mg'],
                ':bac_water_ml' => (float) $row['bac_water_ml'],
                ':concentration' => $conc,
                ':display_name' => (string) $row['name'],
                ':compounded_at' => (string) $row['compounded_at'],
                ':created_at' => (string) $row['created_at'],
            ]);
        }
    }

    private function resolvePeptideId(PDO $pdo, string $slugOrId): string
    {
        $stmt = $pdo->prepare('SELECT id FROM peptides WHERE slug = :slug OR id = :id LIMIT 1');
        $stmt->execute([':slug' => $slugOrId, ':id' => $slugOrId]);
        $found = $stmt->fetchColumn();

        return is_string($found) && $found !== '' ? $found : $slugOrId;
    }

    private function migrateVialPeople(PDO $pdo): void
    {
        if (!$this->tableHasRows($pdo, 'compound_profiles')) {
            return;
        }
        $rows = $pdo->query(
            'SELECT compound_id, profile_id FROM compound_profiles ORDER BY compound_id, profile_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT INTO vial_people (vial_id, person_id, sort_order) VALUES (:vial, :person, :ord)'
        );
        $order = [];
        foreach ($rows as $row) {
            $vialId = (string) $row['compound_id'];
            $order[$vialId] = ($order[$vialId] ?? 0) + 1;
            $insert->execute([
                ':vial' => $vialId,
                ':person' => (string) $row['profile_id'],
                ':ord' => $order[$vialId],
            ]);
        }
    }

    private function applyUsageOffsets(PDO $pdo): void
    {
        $stmt = $pdo->query(
            'SELECT compound_id, COALESCE(SUM(delta_mg), 0) AS total FROM compound_adjustments GROUP BY compound_id'
        );
        $rows = $stmt === false ? [] : $stmt->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE vials SET usage_offset_mg = :offset WHERE id = :id');
        foreach ($rows as $row) {
            $update->execute([
                ':offset' => -(float) $row['total'],
                ':id' => (string) $row['compound_id'],
            ]);
        }
    }

    private function migrateLogEntries(PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT u.id, u.compound_id, u.iu, u.volume_ml, u.peptide_mg, u.syringe_volume_ml, u.syringe_capacity_iu,
                    u.used_at, u.notes, u.created_at, u.updated_at, u.profile_id,
                    p.name AS profile_name,
                    c.peptide_type_name, c.peptide_mg AS vial_mg, c.bac_water_ml, c.name AS compound_name
             FROM uses u
             LEFT JOIN profiles p ON p.id = u.profile_id
             LEFT JOIN compounds c ON c.id = u.compound_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare(
            'INSERT INTO log_entries (
                id, person_id, person_name, vial_id, vial_name, lid_color_name,
                peptide_name, bac_water_ml, peptide_mg_in_vial, concentration_mg_per_ml,
                iu, dose_volume_ml, dose_mg, syringe_volume_ml, syringe_capacity_iu,
                logged_at, is_last_draw, notes, created_at, updated_at
            ) VALUES (
                :id, :person_id, :person_name, :vial_id, :vial_name, \'\',
                :peptide_name, :bac, :vial_mg, :conc,
                :iu, :vol, :dose_mg, :s_vol, :s_cap,
                :logged_at, 0, :notes, :created_at, :updated_at
            )'
        );
        foreach ($rows as $row) {
            $hasCompound = $row['compound_name'] !== null;
            $conc = 0.0;
            if ($hasCompound && (float) $row['bac_water_ml'] > 0) {
                $conc = $this->doses->concentration((float) $row['vial_mg'], (float) $row['bac_water_ml']);
            }
            $personName = $row['profile_name'] !== null ? (string) $row['profile_name'] : DoseConfig::PROFILE_DEFAULT_NAME;
            $insert->execute([
                ':id' => (string) $row['id'],
                ':person_id' => $row['profile_id'] !== null ? (string) $row['profile_id'] : null,
                ':person_name' => $personName,
                ':vial_id' => $hasCompound ? (string) $row['compound_id'] : null,
                ':vial_name' => $hasCompound ? (string) $row['compound_name'] : '',
                ':peptide_name' => $hasCompound ? (string) $row['peptide_type_name'] : '',
                ':bac' => $hasCompound ? (float) $row['bac_water_ml'] : 0.0,
                ':vial_mg' => $hasCompound ? (float) $row['vial_mg'] : 0.0,
                ':conc' => $conc,
                ':iu' => (float) $row['iu'],
                ':vol' => (float) $row['volume_ml'],
                ':dose_mg' => (float) $row['peptide_mg'],
                ':s_vol' => (float) $row['syringe_volume_ml'],
                ':s_cap' => (float) $row['syringe_capacity_iu'],
                ':logged_at' => (string) $row['used_at'],
                ':notes' => $row['notes'],
                ':created_at' => (string) $row['created_at'],
                ':updated_at' => (string) $row['updated_at'],
            ]);
        }
    }

    private function resolveCurrentVials(PDO $pdo): void
    {
        $people = $pdo->query('SELECT id FROM people')->fetchAll(PDO::FETCH_COLUMN);
        if ($people === []) {
            $this->supersedeOrphanVials($pdo);

            return;
        }

        $currentByPerson = [];
        foreach ($people as $personId) {
            $personId = (string) $personId;
            $stmt = $pdo->prepare(
                'SELECT c.id, c.compounded_at
                 FROM compounds c
                 INNER JOIN compound_profiles cp ON cp.compound_id = c.id AND cp.profile_id = :person
                 WHERE c.archived_at IS NULL
                 ORDER BY c.compounded_at DESC, c.id DESC
                 LIMIT 1'
            );
            $stmt->execute([':person' => $personId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $currentByPerson[$personId] = (string) $row['id'];
            }
        }

        $vialCurrentPeople = [];
        foreach ($currentByPerson as $personId => $vialId) {
            $vialCurrentPeople[$vialId][] = $personId;
        }

        $allVials = $pdo->query('SELECT id FROM vials')->fetchAll(PDO::FETCH_COLUMN);
        $deleteLink = $pdo->prepare('DELETE FROM vial_people WHERE vial_id = :vial AND person_id = :person');
        $insertLink = $pdo->prepare(
            'INSERT OR REPLACE INTO vial_people (vial_id, person_id, sort_order) VALUES (:vial, :person, :ord)'
        );
        $updateName = $pdo->prepare('UPDATE vials SET display_name = :name WHERE id = :id');
        $now = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($allVials as $vialId) {
            $vialId = (string) $vialId;
            $assigned = $vialCurrentPeople[$vialId] ?? [];
            if ($assigned === []) {
                continue;
            }
            $pdo->prepare('DELETE FROM vial_people WHERE vial_id = :id')->execute([':id' => $vialId]);
            $names = [];
            foreach (array_values($assigned) as $i => $personId) {
                $insertLink->execute([':vial' => $vialId, ':person' => $personId, ':ord' => $i + 1]);
                $nameStmt = $pdo->prepare('SELECT name FROM people WHERE id = :id');
                $nameStmt->execute([':id' => $personId]);
                $names[] = (string) $nameStmt->fetchColumn();
            }
            $vialRow = $pdo->prepare('SELECT peptide_name, concentration_mg_per_ml FROM vials WHERE id = :id');
            $vialRow->execute([':id' => $vialId]);
            $vRow = $vialRow->fetch(PDO::FETCH_ASSOC);
            if (is_array($vRow)) {
                $syringe = $this->accountSyringe($pdo);
                $updateName->execute([
                    ':name' => $this->namer->build(
                        (string) $vRow['peptide_name'],
                        $names,
                        (float) $vRow['concentration_mg_per_ml'],
                        $syringe['volume_ml'],
                        $syringe['capacity_iu'],
                    ),
                    ':id' => $vialId,
                ]);
            }
        }

        foreach ($currentByPerson as $personId => $chosenVial) {
            $others = $pdo->prepare(
                'SELECT vial_id FROM vial_people vp
                 INNER JOIN vials v ON v.id = vp.vial_id
                 WHERE vp.person_id = :person AND vp.vial_id != :current AND v.superseded_at IS NULL'
            );
            $others->execute([':person' => $personId, ':current' => $chosenVial]);
            while (($oldVial = $others->fetchColumn()) !== false) {
                $deleteLink->execute([':vial' => (string) $oldVial, ':person' => $personId]);
                $this->refreshVialDisplayName($pdo, (string) $oldVial);
            }
        }

        $this->supersedeOrphanVials($pdo);

        $compounds = $pdo->query(
            'SELECT id, archived_at, is_open FROM compounds'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($compounds as $compound) {
            $vialId = (string) $compound['id'];
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM vial_people WHERE vial_id = :id');
            $countStmt->execute([':id' => $vialId]);
            $count = (int) $countStmt->fetchColumn();
            $archived = $compound['archived_at'] !== null;
            $closed = (int) ($compound['is_open'] ?? 1) === 0;
            if ($count === 0 || $archived || $closed) {
                $pdo->prepare(
                    'UPDATE vials SET superseded_at = COALESCE(superseded_at, :now) WHERE id = :id'
                )->execute([':now' => $now, ':id' => $vialId]);
            }
        }
    }

    private function supersedeOrphanVials(PDO $pdo): void
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $rows = $pdo->query(
            'SELECT v.id FROM vials v
             LEFT JOIN vial_people vp ON vp.vial_id = v.id
             WHERE vp.vial_id IS NULL AND v.superseded_at IS NULL'
        )->fetchAll(PDO::FETCH_COLUMN);
        $update = $pdo->prepare('UPDATE vials SET superseded_at = :now WHERE id = :id');
        foreach ($rows as $id) {
            $update->execute([':now' => $now, ':id' => (string) $id]);
        }
    }

    private function refreshVialDisplayName(PDO $pdo, string $vialId): void
    {
        $vial = $pdo->prepare(
            'SELECT peptide_name, concentration_mg_per_ml FROM vials WHERE id = :id'
        );
        $vial->execute([':id' => $vialId]);
        $vRow = $vial->fetch(PDO::FETCH_ASSOC);
        if (!is_array($vRow)) {
            return;
        }
        $people = $pdo->prepare(
            'SELECT p.name FROM vial_people vp INNER JOIN people p ON p.id = vp.person_id
             WHERE vp.vial_id = :id ORDER BY vp.sort_order ASC'
        );
        $people->execute([':id' => $vialId]);
        $names = array_map(static fn ($n): string => (string) $n, $people->fetchAll(PDO::FETCH_COLUMN));
        if ($names === []) {
            return;
        }
        $syringe = $this->accountSyringe($pdo);
        $pdo->prepare('UPDATE vials SET display_name = :name WHERE id = :id')->execute([
            ':name' => $this->namer->build(
                (string) $vRow['peptide_name'],
                $names,
                (float) $vRow['concentration_mg_per_ml'],
                $syringe['volume_ml'],
                $syringe['capacity_iu'],
            ),
            ':id' => $vialId,
        ]);
    }

    /**
     * @return array{volume_ml: float, capacity_iu: float}
     */
    private function accountSyringe(PDO $pdo): array
    {
        return (new AccountSyringeRepository())->get($pdo);
    }

    private function tableHasRows(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }
}