<?php

declare(strict_types=1);

namespace Tests\Application\Actions\LogBook;

use App\Domain\Auth\AuthConfig;
use App\Domain\Dose\DoseConfig;
use App\Domain\LogBook\LogBookConfig;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class LogBookHttpTest extends TestCase
{
    public function testV2RoutesRequireAuth(): void
    {
        $app = $this->getAppInstance();
        $paths = [
            ['GET', '/api/v1/people'],
            ['POST', '/api/v1/people'],
            ['GET', '/api/v1/peptides'],
            ['GET', '/api/v1/syringe'],
            ['GET', '/api/v1/vials'],
            ['GET', '/api/v1/log/defaults?person_id=x'],
        ];
        foreach ($paths as [$method, $path]) {
            $response = $app->handle($this->createRequest($method, $path));
            $this->assertSame(401, $response->getStatusCode(), $path);
        }
    }

    public function testCreateEndpointsReturn201(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $this->assertSame(201, $this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid)->getStatusCode());
        $person = $this->json($this->authed($app, 'GET', '/api/v1/people', $sid))['data'][0];
        $this->assertSame(
            201,
            $this->authedJson($app, 'POST', '/api/v1/vials', [
                'peptide_id' => 'tirzepatide',
                'person_ids' => [$person['id']],
                'peptide_mg' => 10,
                'bac_water_ml' => 2,
                'lid_color_name' => 'Blue',
            ], $sid)->getStatusCode(),
        );
        $vial = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'][0];
        $this->assertSame(
            201,
            $this->authedJson($app, 'POST', '/api/v1/log', [
                'person_id' => $person['id'],
                'vial_id' => $vial['id'],
                'iu' => 10,
                'logged_at' => '2026-08-20T10:00',
            ], $sid)->getStatusCode(),
        );
        $this->assertSame(
            201,
            $this->authedJson($app, 'POST', '/api/v1/peptides', ['name' => 'ExtraPeptide'], $sid)->getStatusCode(),
        );
    }

    public function testFreshAccountPeoplePeptidesAndSyringe(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);

        $people = $this->json($this->authed($app, 'GET', '/api/v1/people', $sid))['data'];
        $this->assertSame([], $people);

        $peptideNames = array_map(
            static fn (array $row): string => $row['name'],
            $this->json($this->authed($app, 'GET', '/api/v1/peptides', $sid))['data'],
        );
        $this->assertSame(['Retatrutide', 'Tirzepatide'], $peptideNames);

        $syringe = $this->json($this->authed($app, 'GET', '/api/v1/syringe', $sid))['data'];
        $this->assertEqualsWithDelta(0.5, $syringe['volume_ml'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $syringe['capacity_iu'], 1e-9);
    }


    public function testCreateVialDerivedNamesAndReplace(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $noah = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $sam = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Sam'], $sid))['data'];

        $solo = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$noah['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $this->assertSame('Tirzepatide - Noah - 5.00 mg/mL', $solo['display_name']);

        $dual = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'retatrutide',
            'person_ids' => [$noah['id'], $sam['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 1,
            'lid_color_name' => 'Red',
        ], $sid))['data'];
        $this->assertSame('Retatrutide - Noah, Sam - 10.00 mg/mL', $dual['display_name']);

        $replace = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$noah['id']],
            'peptide_mg' => 5,
            'bac_water_ml' => 1,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $samOnly = $this->findVialForPerson($vials, $sam['id']);
        $this->assertSame($dual['id'], $samOnly['id']);
        $this->assertStringNotContainsString('Noah', $samOnly['display_name']);
        $this->assertSame($replace['id'], $this->findVialForPerson($vials, $noah['id'])['id']);
    }

    public function testLogSnapshotAndSyringeImmutability(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $noah = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$noah['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $created = $this->json($this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $noah['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
            'is_last_draw' => false,
        ], $sid))['data'];
        $this->assertSame($noah['id'], $created['person_id']);
        $this->assertEqualsWithDelta(2.0, $created['bac_water_ml'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $created['peptide_mg_in_vial'], 1e-9);
        $this->assertEqualsWithDelta(1.25, $created['dose_mg'], 1e-4);
        $this->assertEqualsWithDelta(0.25, $created['dose_volume_ml'], 1e-4);

        $this->authedJson($app, 'PUT', '/api/v1/syringe', ['volume_ml' => 1, 'capacity_iu' => 100], $sid);
        $loaded = $this->json($this->authed($app, 'GET', '/api/v1/log/' . $created['id'], $sid))['data'];
        $this->assertEqualsWithDelta(0.5, $loaded['syringe_volume_ml'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $loaded['syringe_capacity_iu'], 1e-9);
    }

    public function testCrossPersonDrawAndOverdraw(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $noah = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $sam = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Sam'], $sid))['data'];
        $samVial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$sam['id']],
            'peptide_mg' => 2,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];

        $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $noah['id'],
            'vial_id' => $samVial['id'],
            'iu' => 201,
            'logged_at' => '2026-08-20T11:00',
            'is_last_draw' => false,
        ], $sid);
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $vial = $this->findVialForPerson($vials, $sam['id']);
        $this->assertEqualsWithDelta($samVial['id'], $vial['id'], 1e-9);
        $this->assertArrayHasKey($noah['id'], $vial['drawn_mg']);
        $this->assertArrayNotHasKey($sam['id'], $vial['drawn_mg']);
        $this->assertLessThan(0, $vial['mg_left']);
    }

    public function testLogValidationAndLastDraw(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];

        $badPerson = $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => '00000000-0000-4000-8000-000000000099',
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
        ], $sid);
        $this->assertSame(422, $badPerson->getStatusCode());

        $badIu = $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25.55,
            'logged_at' => '2026-08-20T10:00',
        ], $sid);
        $this->assertSame(422, $badIu->getStatusCode());
        $this->assertArrayNotHasKey('remaining_iu', $this->json($badIu)['error'] ?? []);

        $last = $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T12:00',
            'is_last_draw' => true,
        ], $sid);
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $this->assertTrue($vials[0]['probably_empty']);

        $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 10,
            'logged_at' => '2026-08-21T10:00',
            'is_last_draw' => false,
        ], $sid);
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $this->assertFalse($vials[0]['probably_empty']);
    }

    public function testDefaultsAndDelete(): void
    {
        $this->clock = \Tests\Support\FrozenClock::at('2026-11-16T10:18:00Z');
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $emptyDefaults = $this->json($this->authed(
            $app,
            'GET',
            '/api/v1/log/defaults',
            $sid,
            'person_id=' . $person['id'],
        ))['data'];
        $this->assertNull($emptyDefaults['iu']);
        $this->assertNull($emptyDefaults['source']);
        $this->assertSame('2026-11-16T10:18', $emptyDefaults['logged_at']);

        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $logged = $this->json($this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-11-09T09:00',
        ], $sid))['data'];

        $defaults = $this->json($this->authed(
            $app,
            'GET',
            '/api/v1/log/defaults',
            $sid,
            'person_id=' . $person['id'],
        ))['data'];
        $this->assertEqualsWithDelta(25.0, $defaults['iu'], 1e-9);
        $this->assertSame($logged['id'], $defaults['source']['log_id']);

        $delete = $app->handle($this->createRequest(
            'DELETE',
            '/api/v1/log/' . $logged['id'],
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertSame(204, $delete->getStatusCode());
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $this->assertEqualsWithDelta(10.0, $vials[0]['mg_left'], 1e-4);
    }

    public function testSupersededVialRejectedOnLog(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $old = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $new = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 5,
            'bac_water_ml' => 1,
            'lid_color_name' => 'Green',
        ], $sid))['data'];
        $rejected = $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $old['id'],
            'iu' => 10,
            'logged_at' => '2026-08-20T10:00',
        ], $sid);
        $this->assertSame(422, $rejected->getStatusCode());
        $this->assertSame(
            [LogBookConfig::VIAL_NOT_CURRENT],
            $this->json($rejected)['error']['fields']['vial_id'],
        );
        unset($new);
    }

    public function testPatchPersonPeptideAndSyringeValidation(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $renamed = $this->json($this->authedJson($app, 'PATCH', '/api/v1/people/' . $person['id'], [
            'name' => 'Nate',
        ], $sid))['data'];
        $this->assertSame('Nate', $renamed['name']);

        $peptide = $this->json($this->authedJson($app, 'POST', '/api/v1/peptides', ['name' => 'Custom'], $sid))['data'];
        $this->assertSame('Custom', $peptide['name']);
        $this->authedJson($app, 'POST', '/api/v1/peptides/' . $peptide['id'] . '/remove', [], $sid);

        $zero = $this->authedJson($app, 'PUT', '/api/v1/syringe', ['volume_ml' => 0, 'capacity_iu' => 50], $sid);
        $this->assertSame(422, $zero->getStatusCode());
        $this->authedJson($app, 'PUT', '/api/v1/syringe', ['volume_ml' => 0.3, 'capacity_iu' => 30], $sid);
    }

    public function testViewPatchAndListLog(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $row = $this->json($this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
            'notes' => 'weekly',
        ], $sid))['data'];
        $view = $this->json($this->authed($app, 'GET', '/api/v1/log/' . $row['id'], $sid))['data'];
        $this->assertSame('weekly', $view['notes']);
        $patchResponse = $this->authedJson($app, 'PATCH', '/api/v1/log/' . $row['id'], [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 20,
            'logged_at' => '2026-08-20T11:00',
            'is_last_draw' => true,
        ], $sid);
        $this->assertSame(200, $patchResponse->getStatusCode());
        $patched = $this->json($patchResponse)['data'];
        $this->assertEqualsWithDelta(20.0, $patched['iu'], 1e-9);
        $list = $this->json($this->authed(
            $app,
            'GET',
            '/api/v1/log',
            $sid,
            'person_id=' . $person['id'] . '&limit=10',
        ))['data'];
        $this->assertCount(1, $list);
    }

    public function testRestorePersonWhenNameIsFree(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $this->authedJson($app, 'POST', '/api/v1/people/' . $person['id'] . '/remove', [], $sid);
        $this->json($this->authedJson($app, 'POST', '/api/v1/people/' . $person['id'] . '/restore', [], $sid));
        $this->assertCount(1, $this->json($this->authed($app, 'GET', '/api/v1/people', $sid))['data']);
    }

    public function testPeopleDuplicateRemoveRestore(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $a = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];
        $dup = $this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'noah'], $sid);
        $this->assertSame(422, $dup->getStatusCode());

        $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$a['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid));
        $this->authedJson($app, 'POST', '/api/v1/people/' . $a['id'] . '/remove', [], $sid);
        $vials = $this->json($this->authed($app, 'GET', '/api/v1/vials', $sid))['data'];
        $this->assertSame([], $vials);

        $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid));
        $clash = $this->authedJson($app, 'POST', '/api/v1/people/' . $a['id'] . '/restore', [], $sid);
        $this->assertSame(422, $clash->getStatusCode());
    }

    public function testLogsMigrationApplied(): void
    {
        $handler = new TestHandler();
        $app = $this->getAppInstance(static function ($builder) use ($handler): void {
            $logger = new Logger('test');
            $logger->pushHandler($handler);
            $builder->addDefinitions([LoggerInterface::class => static fn (): LoggerInterface => $logger]);
        });
        $sid = $this->register($app);
        $this->authed($app, 'GET', '/api/v1/people', $sid);
        $this->assertTrue($handler->hasInfoThatContains('logbook.migration.applied'));
    }

    public function testLogsVialCreated(): void
    {
        [$app, $handler, $sid, $person] = $this->appWithLogHandler();
        $this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid);
        $this->assertTrue($handler->hasInfoThatContains('logbook.vial.created'));
    }

    public function testLogsEntrySaved(): void
    {
        [$app, $handler, $sid, $person] = $this->appWithLogHandler();
        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $handler->clear();
        $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
        ], $sid);
        $this->assertTrue($handler->hasInfoThatContains('logbook.entry.saved'));
    }

    public function testLogsEntryRejected(): void
    {
        [$app, $handler, $sid, $person] = $this->appWithLogHandler();
        $handler->clear();
        $this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => '00000000-0000-4000-8000-000000000099',
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
        ], $sid);
        $this->assertTrue($handler->hasInfoThatContains('logbook.entry.rejected'));
    }

    public function testLogsEntryDeleted(): void
    {
        [$app, $handler, $sid, $person] = $this->appWithLogHandler();
        $vial = $this->json($this->authedJson($app, 'POST', '/api/v1/vials', [
            'peptide_id' => 'tirzepatide',
            'person_ids' => [$person['id']],
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'lid_color_name' => 'Blue',
        ], $sid))['data'];
        $row = $this->json($this->authedJson($app, 'POST', '/api/v1/log', [
            'person_id' => $person['id'],
            'vial_id' => $vial['id'],
            'iu' => 25,
            'logged_at' => '2026-08-20T10:00',
        ], $sid))['data'];
        $handler->clear();
        $app->handle($this->createRequest(
            'DELETE',
            '/api/v1/log/' . $row['id'],
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
        $this->assertTrue($handler->hasInfoThatContains('logbook.entry.deleted'));
    }

    /**
     * @param list<array<string, mixed>> $vials
     * @return array<string, mixed>
     */
    private function findVialForPerson(array $vials, string $personId): array
    {
        foreach ($vials as $vial) {
            foreach ($vial['people'] as $person) {
                if ($person['person_id'] === $personId) {
                    return $vial;
                }
            }
        }
        $this->fail('vial not found for person');

        return [];
    }

    /**
     * @return array{0: \Slim\App, 1: TestHandler, 2: string, 3: array<string, mixed>}
     */
    private function appWithLogHandler(): array
    {
        $handler = new TestHandler();
        $app = $this->getAppInstance(static function ($builder) use ($handler): void {
            $logger = new Logger('test');
            $logger->pushHandler($handler);
            $builder->addDefinitions([LoggerInterface::class => static fn (): LoggerInterface => $logger]);
        });
        $sid = $this->register($app);
        $person = $this->json($this->authedJson($app, 'POST', '/api/v1/people', ['name' => 'Noah'], $sid))['data'];

        return [$app, $handler, $sid, $person];
    }

    protected function register(\Slim\App $app): string
    {
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => bin2hex(random_bytes(4)) . '@logbook.test',
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(201, $response->getStatusCode());

        return $this->sessionIdFrom($response);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function authedJson(
        \Slim\App $app,
        string $method,
        string $path,
        array $body,
        string $sid,
    ): \Psr\Http\Message\ResponseInterface {
        return $app->handle($this->createJsonRequest(
            $method,
            $path,
            $body,
            [AuthConfig::SESSION_COOKIE => $sid],
        ));
    }

    protected function authed(
        \Slim\App $app,
        string $method,
        string $path,
        string $sid,
        string $query = '',
    ): \Psr\Http\Message\ResponseInterface {
        return $app->handle($this->createRequest(
            $method,
            $path,
            ['HTTP_ACCEPT' => 'application/json'],
            [AuthConfig::SESSION_COOKIE => $sid],
            [],
            $query,
        ));
    }
}