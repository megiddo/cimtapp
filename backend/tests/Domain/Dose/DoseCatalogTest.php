<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Dose\DoseConfig;
use App\Domain\Dose\DoseFormatter;
use App\Domain\Dose\DoseLimits;
use App\Domain\Dose\DoseMessages;
use Tests\TestCase;

class DoseCatalogTest extends TestCase
{
    public function testLimitsMatchProductPolicy(): void
    {
        $this->assertSame(4, DoseLimits::MG_DECIMALS);
        $this->assertSame(6, DoseLimits::VOLUME_DECIMALS);
        $this->assertSame(1, DoseLimits::IU_DECIMALS);
        $this->assertSame(0.5, DoseLimits::CONCENTRATION_WARN_LOW);
        $this->assertSame(20.0, DoseLimits::CONCENTRATION_WARN_HIGH);
        $this->assertSame(50, DoseLimits::USES_DEFAULT_LIMIT);
        $this->assertSame(100, DoseLimits::USES_MAX_LIMIT);
        $this->assertSame(0.5, DoseLimits::FALLBACK_SYRINGE_VOLUME_ML);
        $this->assertSame(50.0, DoseLimits::FALLBACK_SYRINGE_CAPACITY_IU);
        $this->assertSame(80, DoseLimits::VIAL_NAME_MAX);
        $this->assertSame(5, DoseLimits::PROFILE_MAX);
        $this->assertSame(40, DoseLimits::PROFILE_NAME_MAX);
        $this->assertSame(DoseLimits::MG_DECIMALS, DoseConfig::MG_DECIMALS);
        $this->assertSame(DoseLimits::PROFILE_MAX, DoseConfig::PROFILE_MAX);
    }

    public function testMessagesKeepProductCopy(): void
    {
        $this->assertSame('IU must be greater than 0.', DoseMessages::IU_NOT_POSITIVE);
        $this->assertSame('IU allows one decimal place.', DoseMessages::IU_ONE_DECIMAL);
        $this->assertSame('Must be greater than 0.', DoseMessages::MUST_BE_POSITIVE);
        $this->assertSame('Must be 0 or greater.', DoseMessages::MUST_BE_NON_NEGATIVE);
        $this->assertSame('Enter a number greater than 0.', DoseMessages::MUST_BE_NUMBER);
        $this->assertSame('Must be text.', DoseMessages::MUST_BE_TEXT);
        $this->assertSame('Must be true or false.', DoseMessages::MUST_BE_BOOLEAN);
        $this->assertSame('Enter a valid date and time.', DoseMessages::MUST_BE_DATETIME);
        $this->assertSame('Choose a peptide from the catalog.', DoseMessages::PEPTIDE_UNKNOWN);
        $this->assertSame('That peptide is already on the list.', DoseMessages::PEPTIDE_NAME_TAKEN);
        $this->assertSame('Use a name of 80 characters or fewer.', DoseMessages::PEPTIDE_NAME_TOO_LONG);
        $this->assertSame('Use a name of 80 characters or fewer.', DoseMessages::VIAL_NAME_TOO_LONG);
        $this->assertSame('Mix a vial before logging a use.', DoseMessages::NO_COMPOUND);
        $this->assertSame('Compound not found.', DoseMessages::COMPOUND_UNKNOWN);
        $this->assertSame('Syringe not found.', DoseMessages::SYRINGE_UNKNOWN);
        $this->assertSame('No syringes remaining of this type.', DoseMessages::SYRINGE_STOCK_EMPTY);
        $this->assertSame('Bacteriostatic water bottle not found.', DoseMessages::BAC_UNKNOWN);
        $this->assertSame('Add a bacteriostatic water bottle before mixing a vial.', DoseMessages::NO_BAC_BOTTLE);
        $this->assertSame('This bottle has been used and cannot be deleted.', DoseMessages::BAC_IN_USE);
        $this->assertSame('Enter a whole number greater than 0.', DoseMessages::MUST_BE_WHOLE);
        $this->assertSame('Use not found.', DoseMessages::USE_UNKNOWN);
        $this->assertSame('Keep one default syringe.', DoseMessages::DEFAULT_REQUIRED);
        $this->assertSame('Keep at least one syringe type.', DoseMessages::SYRINGE_LAST);
        $this->assertSame('This vial has logged uses and cannot be deleted.', DoseMessages::COMPOUND_HAS_USES);
        $this->assertSame(
            'Existing uses would exceed this mix. Reduce those uses or increase peptide milligrams.',
            DoseMessages::COMPOUND_OVERDRAW
        );
        $this->assertSame('This vial is archived.', DoseMessages::COMPOUND_ARCHIVED);
        $this->assertSame('Already archived.', DoseMessages::ALREADY_ARCHIVED);
        $this->assertSame('Archive is available when remaining is 0.', DoseMessages::ARCHIVE_NOT_EMPTY);
        $this->assertSame('Remaining cannot exceed the mix volume.', DoseMessages::REMAINING_EXCEEDS_MIX);
        $this->assertSame('Limit must be between 1 and 100.', DoseMessages::LIMIT_INVALID);
        $this->assertSame('before must be an ISO timestamp.', DoseMessages::BEFORE_INVALID);
        $this->assertSame('Default', DoseMessages::PROFILE_DEFAULT_NAME);
        $this->assertSame('Profile not found.', DoseMessages::PROFILE_UNKNOWN);
        $this->assertSame('Use a name of 40 characters or fewer.', DoseMessages::PROFILE_NAME_TOO_LONG);
        $this->assertSame('You can add up to 4 additional profiles.', DoseMessages::PROFILE_LIMIT);
        $this->assertSame('Keep the default profile.', DoseMessages::PROFILE_DEFAULT_REQUIRED);
        $this->assertSame('Move this profile’s uses before deleting it.', DoseMessages::PROFILE_HAS_USES);
        $this->assertSame('This vial is not associated with that profile.', DoseMessages::PROFILE_VIAL_MISMATCH);
        $this->assertSame('Choose one or more profiles.', DoseMessages::MUST_BE_ID_LIST);
        $this->assertSame('Choose at least one profile.', DoseMessages::PROFILE_REQUIRED);
        $this->assertSame(DoseMessages::COMPOUND_UNKNOWN, DoseConfig::COMPOUND_UNKNOWN);
        $this->assertSame(DoseMessages::PROFILE_DEFAULT_NAME, DoseConfig::PROFILE_DEFAULT_NAME);
    }

    public function testFormatterCopyIsPinned(): void
    {
        $this->assertSame('12 IU exceeds 3 IU remaining in this vial.', DoseFormatter::overdraw('12', '3'));
        $this->assertSame(
            '2 mL exceeds 1 mL remaining in bacteriostatic water.',
            DoseFormatter::bacOverdraw('2', '1')
        );
        $this->assertSame('3 exceeds 1 syringes remaining.', DoseFormatter::syringeOverdraw(3, 1));
        $this->assertSame('5', DoseFormatter::formatIu(5.0));
        $this->assertSame('5.5', DoseFormatter::formatIu(5.5));
        $this->assertSame('0.5 mL / 50 IU', DoseFormatter::syringeLabel(0.5, 50));
        $this->assertSame('1.25', DoseFormatter::trimNumber(1.2500));
    }
}
