<?php

declare(strict_types=1);

namespace App\Domain\Dose;

use App\Domain\Auth\Clock;
use App\Domain\Auth\IdGenerator;
use DateTimeZone;
use PDO;

final class ProfileService
{
    private readonly ProfileRepository $repository;
    private readonly ProfilePolicy $policy;
    private readonly CompoundProfileLinker $linker;

    public function __construct(
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {
        $this->repository = new ProfileRepository();
        $this->policy = new ProfilePolicy($this->repository);
        $this->linker = new CompoundProfileLinker($this->policy);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(PDO $pdo): array
    {
        return $this->repository->list($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(PDO $pdo, string $id): array
    {
        return $this->policy->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(PDO $pdo, string $id): ?array
    {
        return $this->repository->find($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultProfile(PDO $pdo): array
    {
        return $this->policy->defaultProfile($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function require(PDO $pdo, string $id): array
    {
        return $this->policy->require($pdo, $id);
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    public function requireIds(PDO $pdo, array $ids): array
    {
        return $this->policy->requireIds($pdo, $ids);
    }

    public function compoundLinked(PDO $pdo, string $compoundId, string $profileId): bool
    {
        return $this->linker->compoundLinked($pdo, $compoundId, $profileId);
    }

    /**
     * @return list<string>
     */
    public function profileIdsForCompound(PDO $pdo, string $compoundId): array
    {
        return $this->linker->profileIdsForCompound($pdo, $compoundId);
    }

    /**
     * @param list<string> $profileIds
     */
    public function replaceCompoundProfiles(PDO $pdo, string $compoundId, array $profileIds): void
    {
        $this->linker->replaceCompoundProfiles($pdo, $compoundId, $profileIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function create(PDO $pdo, FieldParser $fields): array
    {
        $this->policy->assertCanCreate($pdo);
        $name = $this->policy->requireName($fields);
        $id = $this->ids->uuid();
        $this->repository->insert($pdo, [
            ':id' => $id,
            ':name' => $name,
            ':created_at' => $this->timestamp(),
        ]);

        return $this->get($pdo, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function patch(PDO $pdo, string $id, FieldParser $fields): array
    {
        $this->get($pdo, $id);
        $this->repository->updateName($pdo, $id, $this->policy->requireName($fields));

        return $this->get($pdo, $id);
    }

    public function delete(PDO $pdo, string $id): void
    {
        $existing = $this->get($pdo, $id);
        $this->policy->assertCanDelete($pdo, $existing);
        $this->repository->delete($pdo, $id);
    }

    private function timestamp(): string
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
