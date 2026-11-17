<?php

declare(strict_types=1);

namespace App\Domain\LogBook;

final class LogBookConfig
{
    public const LOG_DEFAULT_LIMIT = 50;

    public const LOG_MAX_LIMIT = 100;

    public const PERSON_NAME_TAKEN = 'That name is already in use.';

    public const PERSON_UNKNOWN = 'Person not found.';

    public const PERSON_REMOVED = 'That person was removed.';

    public const PEPTIDE_UNKNOWN = 'Peptide not found.';

    public const PEPTIDE_NAME_TAKEN = 'That peptide name is already in use.';

    public const VIAL_UNKNOWN = 'Vial not found.';

    public const VIAL_NOT_CURRENT = 'That vial is no longer current.';

    public const LOG_UNKNOWN = 'Log entry not found.';

    public const LID_COLOR_REQUIRED = 'Choose or add a lid color.';

    public const LID_COLOR_UNKNOWN = 'Lid color not found.';

    public const PERSON_IDS_REQUIRED = 'Choose at least one person.';

    public const SLUG_TIRZEPATIDE = 'tirzepatide';

    public const SLUG_RETATRUTIDE = 'retatrutide';

    public const NAME_TIRZEPATIDE = 'Tirzepatide';

    public const NAME_RETATRUTIDE = 'Retatrutide';
}