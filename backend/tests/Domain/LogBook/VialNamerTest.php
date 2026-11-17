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
            'Tirzepatide - Noah - 0.05 mg/IU',
            $namer->build('Tirzepatide', ['Noah'], 5.0, 0.5, 50.0),
        );
        $this->assertSame(
            'Retatrutide - Noah, Sam - 0.10 mg/IU',
            $namer->build('Retatrutide', ['Noah', 'Sam'], 10.0, 0.5, 50.0),
        );
    }
}