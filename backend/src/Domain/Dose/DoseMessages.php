<?php

declare(strict_types=1);

namespace App\Domain\Dose;

final class DoseMessages
{
    public const IU_NOT_POSITIVE = 'IU must be greater than 0.';

    public const IU_ONE_DECIMAL = 'IU allows one decimal place.';

    public const MUST_BE_POSITIVE = 'Must be greater than 0.';

    public const MUST_BE_NON_NEGATIVE = 'Must be 0 or greater.';

    public const MUST_BE_NUMBER = 'Enter a number greater than 0.';

    public const MUST_BE_TEXT = 'Must be text.';

    public const MUST_BE_BOOLEAN = 'Must be true or false.';

    public const MUST_BE_DATETIME = 'Enter a valid date and time.';

    public const PEPTIDE_UNKNOWN = 'Choose a peptide from the catalog.';

    public const PEPTIDE_NAME_TAKEN = 'That peptide is already on the list.';

    public const PEPTIDE_NAME_TOO_LONG = 'Use a name of 80 characters or fewer.';

    public const VIAL_NAME_TOO_LONG = 'Use a name of 80 characters or fewer.';

    public const NO_COMPOUND = 'Mix a vial before logging a use.';

    public const COMPOUND_UNKNOWN = 'Compound not found.';

    public const SYRINGE_UNKNOWN = 'Syringe not found.';

    public const SYRINGE_STOCK_EMPTY = 'No syringes remaining of this type.';

    public const BAC_UNKNOWN = 'Bacteriostatic water bottle not found.';

    public const NO_BAC_BOTTLE = 'Add a bacteriostatic water bottle before mixing a vial.';

    public const BAC_IN_USE = 'This bottle has been used and cannot be deleted.';

    public const MUST_BE_WHOLE = 'Enter a whole number greater than 0.';

    public const USE_UNKNOWN = 'Use not found.';

    public const DEFAULT_REQUIRED = 'Keep one default syringe.';

    public const SYRINGE_LAST = 'Keep at least one syringe type.';

    public const COMPOUND_HAS_USES = 'This vial has logged uses and cannot be deleted.';

    public const COMPOUND_OVERDRAW = 'Existing uses would exceed this mix. Reduce those uses or increase peptide milligrams.';

    public const COMPOUND_ARCHIVED = 'This vial is archived.';

    public const ALREADY_ARCHIVED = 'Already archived.';

    public const ARCHIVE_NOT_EMPTY = 'Archive is available when remaining is 0.';

    public const REMAINING_EXCEEDS_MIX = 'Remaining cannot exceed the mix volume.';

    public const LIMIT_INVALID = 'Limit must be between 1 and 100.';

    public const BEFORE_INVALID = 'before must be an ISO timestamp.';

    public const PROFILE_DEFAULT_NAME = 'Default';

    public const PROFILE_UNKNOWN = 'Profile not found.';

    public const PROFILE_NAME_TOO_LONG = 'Use a name of 40 characters or fewer.';

    public const PROFILE_LIMIT = 'You can add up to 4 additional profiles.';

    public const PROFILE_DEFAULT_REQUIRED = 'Keep the default profile.';

    public const PROFILE_HAS_USES = 'Move this profile’s uses before deleting it.';

    public const PROFILE_VIAL_MISMATCH = 'This vial is not associated with that profile.';

    public const MUST_BE_ID_LIST = 'Choose one or more profiles.';

    public const PROFILE_REQUIRED = 'Choose at least one profile.';
}
