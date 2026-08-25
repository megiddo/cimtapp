<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\UserSchema;

final class AddUserPeptideTypes extends SqlFileUserSchemaStrategy
{
    public function version(): UserStoreFormat
    {
        return UserStoreFormat::V3UserPeptideTypes;
    }

    protected function filename(): string
    {
        return '003_user_peptide_types.sql';
    }
}
