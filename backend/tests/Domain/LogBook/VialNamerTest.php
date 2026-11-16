<?php

declare(strict_types=1);

namespace Tests\Domain\LogBook;

use App\Domain\LogBook\VialNamer;
use PHPUnit\Framework\TestCase;

final class VialNamerTest extends TestCase
{
    public function testBuildFormatsExamplesFromSpec(): void
    {
        $namer = new VialNamer();
        $this->assertSame(
            'Tirzepatide - Noah - 5.00 mg/mL',
            $namer->build('Tirzepatide', ['Noah'], 5.0),
        );
        $this->assertSame(
            'Retatrutide - Noah, Sam - 10.00 mg/mL',
            $namer->build('Retatrutide', ['Noah', 'Sam'], 10.0),
        );
    }
}