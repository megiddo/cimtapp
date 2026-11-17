<?php

declare(strict_types=1);

namespace Tests\Application\Actions\LogBook;

final class LegacyRoutesAbsentTest extends LogBookHttpTest
{
    public function testRoutesFileDoesNotMountV03StockApi(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../../../app/routes.php');
        foreach (['/compounds', '/uses', '/bac-bottles', '/profiles', '/peptide-types', '/syringes'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $routes, $legacy);
        }
    }

    public function testMePayloadIsIdentityOnly(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $json = $this->json($this->authed($app, 'GET', '/api/v1/me', $sid));
        $this->assertArrayNotHasKey('remainder', $json['data']);
        $this->assertArrayNotHasKey('open_vials', $json['data']);
    }
}