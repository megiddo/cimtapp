<?php

declare(strict_types=1);

namespace Tests\Domain\Dose;

use App\Domain\Dose\StockList;
use Tests\TestCase;

class StockListTest extends TestCase
{
    public function testFromQueryTreatsViewAllAsGrouped(): void
    {
        $this->assertTrue(StockList::fromQuery(['view' => 'all'])->includesArchived());
        $this->assertFalse(StockList::fromQuery([])->includesArchived());
        $this->assertFalse(StockList::fromQuery(['view' => 'open'])->includesArchived());
        $this->assertFalse(StockList::fromQuery(['view' => 'ALL'])->includesArchived());
        $this->assertFalse(StockList::openOnly()->includesArchived());
        $this->assertTrue(StockList::allGrouped()->includesArchived());
    }
}
