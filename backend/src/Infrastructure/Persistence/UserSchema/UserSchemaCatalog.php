<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

/**
 * Ordered strategies that mutate a user sqlite to {@see UserStoreFormat::current()}.
 */
final class UserSchemaCatalog
{
    /**
     * @param list<UserSchemaStrategy> $strategies
     */
    public function __construct(private readonly array $strategies)
    {
    }

    public static function default(?string $migrationsDir = null): self
    {
        $dir = $migrationsDir ?? self::migrationsDirectory();

        return new self(self::ordered([
            new CreateInitialUserSchema($dir),
            new AddBacAndSyringeStock($dir),
            new AddUserPeptideTypes($dir),
            new AddNamedOpenVials($dir),
        ]));
    }

    public static function through(UserStoreFormat $max, ?string $migrationsDir = null): self
    {
        $kept = [];
        foreach (self::default($migrationsDir)->strategies() as $strategy) {
            if ($strategy->version()->value <= $max->value) {
                $kept[] = $strategy;
            }
        }

        return new self($kept);
    }

    public static function migrationsDirectory(): string
    {
        return dirname(__DIR__, 4) . '/migrations/user';
    }

    /**
     * @return list<UserSchemaStrategy>
     */
    public function strategies(): array
    {
        return $this->strategies;
    }

    /**
     * @param list<UserSchemaStrategy> $strategies
     * @return list<UserSchemaStrategy>
     */
    private static function ordered(array $strategies): array
    {
        usort(
            $strategies,
            static fn (UserSchemaStrategy $a, UserSchemaStrategy $b): int => $a->version()->value <=> $b->version()->value
        );

        return $strategies;
    }
}
