<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Dose;

use App\Domain\Auth\AuthConfig;
use App\Domain\Dose\DoseConfig;
use Tests\TestCase;

class DoseHttpTest extends TestCase
{
    public function testPeptideTypesAreActiveSortedAndRequireAuth(): void
    {
        $app = $this->getAppInstance();
        $unauth = $app->handle($this->createRequest('GET', '/api/v1/peptide-types'));
        $this->assertSame(401, $unauth->getStatusCode());

        $sid = $this->register($app);
        $response = $this->authed($app, 'GET', '/api/v1/peptide-types', $sid);
        $this->assertSame(200, $response->getStatusCode());
        $names = array_map(static fn (array $row): string => $row['name'], $this->json($response)['data']);
        $this->assertSame(['Semaglutide', 'Tirzepatide', 'Retatrutide', 'Liraglutide'], $names);
        $this->assertSame('tirzepatide', $this->json($response)['data'][1]['id']);
        $this->assertSame('tirzepatide', $this->json($response)['data'][1]['slug']);
    }

    public function testCurrentIs404UntilAVialIsMixedThenRemainderUsesDefaultSyringe(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);

        $missing = $this->authed($app, 'GET', '/api/v1/compounds/current', $sid);
        $this->assertSame(404, $missing->getStatusCode());

        $me = $this->json($this->authed($app, 'GET', '/api/v1/me', $sid));
        $this->assertNull($me['data']['remainder']);

        $noUse = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 25], $sid);
        $this->assertSame(422, $noUse->getStatusCode());
        $this->assertSame(['compound_id' => [DoseConfig::NO_COMPOUND]], $this->json($noUse)['error']['fields']);

        $mixed = $this->mixTirzepatide($app, $sid, '2026-08-19T12:00');
        $this->assertSame(201, $mixed->getStatusCode());
        $compound = $this->json($mixed)['data'];
        $this->assertSame('Tirzepatide', $compound['peptide_type_name']);
        $this->assertSame('tirzepatide', $compound['peptide_type_slug']);
        $this->assertEqualsWithDelta(5.0, $compound['concentration'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $compound['remaining_mg'], 1e-9);
        $this->assertEqualsWithDelta(200.0, $compound['remaining_iu'], 1e-9);
        $this->assertFalse($compound['has_uses']);

        $current = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertSame($compound['id'], $current['id']);
        $this->assertEqualsWithDelta(200.0, $current['remaining_iu'], 1e-9);

        $meAfter = $this->json($this->authed($app, 'GET', '/api/v1/me', $sid))['data']['remainder'];
        $this->assertSame($compound['id'], $meAfter['compound_id']);
        $this->assertSame('Tirzepatide', $meAfter['peptide_name']);
        $this->assertEqualsWithDelta(10.0, $meAfter['remaining_mg'], 1e-9);
    }

    public function testCurrentIsLatestCompoundedAtNotCreatedAt(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $olderMixDate = $this->mixTirzepatide($app, $sid, '2026-08-18T10:00');
        $this->clock->advance(60);
        $newerMixDate = $this->mixTirzepatide($app, $sid, '2026-08-20T10:00', 5.0, 1.0);
        $this->clock->advance(60);
        $createdLastButDatedEarlier = $this->mixTirzepatide($app, $sid, '2026-08-19T10:00', 8.0, 2.0);

        $current = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertSame($this->json($newerMixDate)['data']['id'], $current['id']);
        $this->assertNotSame($this->json($createdLastButDatedEarlier)['data']['id'], $current['id']);
        $this->assertNotSame($this->json($olderMixDate)['data']['id'], $current['id']);
        $this->assertSame('2026-08-20T10:00', $current['compounded_at']);

        $list = $this->json($this->authed($app, 'GET', '/api/v1/compounds', $sid))['data'];
        $this->assertSame(
            ['2026-08-20T10:00', '2026-08-19T10:00', '2026-08-18T10:00'],
            array_map(static fn (array $row): string => $row['compounded_at'], $list)
        );
    }

    public function testLogUseWorkedExampleOverdrawBoundaryAndEditRemainder(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $this->mixTirzepatide($app, $sid, '2026-08-20T12:00');

        $logged = $this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 25,
            'used_at' => '2026-08-20T16:00',
            'notes' => 'first',
        ], $sid);
        $this->assertSame(201, $logged->getStatusCode());
        $use = $this->json($logged)['data'];
        $this->assertEqualsWithDelta(25.0, $use['iu'], 1e-9);
        $this->assertEqualsWithDelta(0.25, $use['volume_ml'], 1e-9);
        $this->assertEqualsWithDelta(1.25, $use['peptide_mg'], 1e-9);
        $this->assertSame('0.5 mL / 50 IU', $use['syringe_label']);
        $this->assertSame('Tirzepatide', $use['peptide_type_name']);

        $current = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertEqualsWithDelta(8.75, $current['remaining_mg'], 1e-9);
        $this->assertEqualsWithDelta(1.75, $current['remaining_ml'], 1e-9);
        $this->assertEqualsWithDelta(175.0, $current['remaining_iu'], 1e-9);
        $this->assertTrue($current['has_uses']);

        $over = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 175.1], $sid);
        $this->assertSame(422, $over->getStatusCode());
        $error = $this->json($over)['error'];
        $this->assertSame('VALIDATION_ERROR', $error['type']);
        $this->assertArrayHasKey('iu', $error['fields']);
        $this->assertEqualsWithDelta(175.0, $error['remaining_iu'], 1e-9);
        $this->assertSame(
            DoseConfig::overdraw('175.1', '175'),
            $error['fields']['iu'][0]
        );
        $this->assertSame($error['fields']['iu'][0], $error['description']);

        $exact = $this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 175,
            'used_at' => '2026-08-20T17:00',
        ], $sid);
        $this->assertSame(201, $exact->getStatusCode());
        $empty = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertEqualsWithDelta(0.0, $empty['remaining_mg'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $empty['remaining_iu'], 1e-9);

        $editDown = $this->authedJson($app, 'PATCH', '/api/v1/uses/' . $use['id'], ['iu' => 10], $sid);
        $this->assertSame(200, $editDown->getStatusCode());
        $this->assertEqualsWithDelta(0.5, $this->json($editDown)['data']['peptide_mg'], 1e-9);
        $afterDown = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertGreaterThan($empty['remaining_mg'], $afterDown['remaining_mg']);
        $this->assertEqualsWithDelta(0.75, $afterDown['remaining_mg'], 1e-9);

        $editUpOver = $this->authedJson($app, 'PATCH', '/api/v1/uses/' . $use['id'], ['iu' => 30], $sid);
        $this->assertSame(422, $editUpOver->getStatusCode());
        $this->assertArrayHasKey('remaining_iu', $this->json($editUpOver)['error']);
        $this->assertArrayHasKey('iu', $this->json($editUpOver)['error']['fields']);
    }

    public function testEditKeepsOriginalVialAndRejectsIuNotPositive(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $first = $this->json($this->mixTirzepatide($app, $sid, '2026-08-18T10:00'))['data'];
        $use = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 10,
            'compound_id' => $first['id'],
            'used_at' => '2026-08-18T11:00',
        ], $sid))['data'];
        $second = $this->json($this->mixTirzepatide($app, $sid, '2026-08-20T10:00'))['data'];

        $patched = $this->json($this->authedJson($app, 'PATCH', '/api/v1/uses/' . $use['id'], [
            'iu' => 12,
            'notes' => 'kept vial',
        ], $sid))['data'];
        $this->assertSame($first['id'], $patched['compound_id']);
        $this->assertNotSame($second['id'], $patched['compound_id']);
        $this->assertSame('kept vial', $patched['notes']);

        $zero = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 0], $sid);
        $this->assertSame(422, $zero->getStatusCode());
        $this->assertSame(['iu' => [DoseConfig::IU_NOT_POSITIVE]], $this->json($zero)['error']['fields']);
    }

    public function testCompoundLockedAfterFirstUseAndEditableBefore(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $id = $this->json($this->mixTirzepatide($app, $sid, '2026-08-20T12:00'))['data']['id'];

        $full = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $id, [
            'peptide_mg' => 12,
            'bac_water_ml' => 2.4,
            'peptide_type_id' => 'semaglutide',
            'notes' => 'pre-use',
        ], $sid);
        $this->assertSame(200, $full->getStatusCode());
        $this->assertSame('Semaglutide', $this->json($full)['data']['peptide_type_name']);
        $this->assertEqualsWithDelta(12.0, $this->json($full)['data']['peptide_mg'], 1e-9);

        $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 5], $sid);

        $locked = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $id, [
            'peptide_mg' => 20,
            'bac_water_ml' => 4,
            'peptide_type_id' => 'tirzepatide',
        ], $sid);
        $this->assertSame(422, $locked->getStatusCode());
        $fields = $this->json($locked)['error']['fields'];
        $this->assertSame([DoseConfig::MG_LOCKED], $fields['peptide_mg']);
        $this->assertSame([DoseConfig::BAC_LOCKED], $fields['bac_water_ml']);
        $this->assertSame([DoseConfig::PEPTIDE_LOCKED], $fields['peptide_type_id']);

        $notesOnly = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $id, [
            'notes' => 'after use',
            'compounded_at' => '2026-08-21T08:00',
            'peptide_mg' => 12,
        ], $sid);
        $this->assertSame(200, $notesOnly->getStatusCode());
        $this->assertSame('after use', $this->json($notesOnly)['data']['notes']);
        $this->assertSame('2026-08-21T08:00', $this->json($notesOnly)['data']['compounded_at']);
        $this->assertEqualsWithDelta(12.0, $this->json($notesOnly)['data']['peptide_mg'], 1e-9);

        $view = $this->json($this->authed($app, 'GET', '/api/v1/compounds/' . $id, $sid))['data'];
        $this->assertSame($id, $view['id']);
        $missing = $this->authed($app, 'GET', '/api/v1/compounds/not-a-real-id', $sid);
        $this->assertSame(404, $missing->getStatusCode());
    }

    public function testSyringeCreateDefaultUniquenessAndAutoLabel(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);

        $list = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'];
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['is_default']);
        $seededId = $list[0]['id'];

        $created = $this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 1,
            'capacity_iu' => 40,
        ], $sid);
        $this->assertSame(201, $created->getStatusCode());
        $new = $this->json($created)['data'];
        $this->assertSame('1 mL / 40 IU', $new['label']);
        $this->assertFalse($new['is_default']);

        $asDefault = $this->authedJson($app, 'PATCH', '/api/v1/syringes/' . $new['id'], [
            'is_default' => true,
            'label' => 'U-40',
        ], $sid);
        $this->assertSame(200, $asDefault->getStatusCode());
        $all = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'];
        $defaults = array_values(array_filter($all, static fn (array $row): bool => $row['is_default'] === true));
        $this->assertCount(1, $defaults);
        $this->assertSame($new['id'], $defaults[0]['id']);
        $this->assertSame('U-40', $defaults[0]['label']);

        $unset = $this->authedJson($app, 'PATCH', '/api/v1/syringes/' . $new['id'], [
            'is_default' => false,
        ], $sid);
        $this->assertSame(422, $unset->getStatusCode());
        $this->assertSame(['is_default' => [DoseConfig::DEFAULT_REQUIRED]], $this->json($unset)['error']['fields']);

        $createDefault = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 0.3,
            'capacity_iu' => 30,
            'is_default' => true,
        ], $sid))['data'];
        $this->assertTrue($createDefault['is_default']);
        $again = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'];
        $this->assertCount(1, array_filter($again, static fn (array $row): bool => $row['is_default'] === true));

        $bad = $this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 0,
            'capacity_iu' => 50,
        ], $sid);
        $this->assertSame(422, $bad->getStatusCode());

        $emptyLabel = $this->authedJson($app, 'PATCH', '/api/v1/syringes/' . $new['id'], [
            'label' => '',
        ], $sid);
        $this->assertSame(422, $emptyLabel->getStatusCode());

        $missing = $this->authedJson($app, 'PATCH', '/api/v1/syringes/missing', ['label' => 'x'], $sid);
        $this->assertSame(404, $missing->getStatusCode());
        $original = array_values(array_filter($again, static fn (array $row): bool => $row['id'] === $seededId));
        $this->assertCount(1, $original);
        $this->assertFalse($original[0]['is_default']);
    }

    public function testUsesNewestFirstLimitBeforeAndLastUsedSyringe(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $this->mixTirzepatide($app, $sid, '2026-08-20T12:00');
        $alt = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 1,
            'capacity_iu' => 40,
            'label' => 'alt',
        ], $sid))['data'];

        $early = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 5,
            'used_at' => '2026-08-20T10:00:00Z',
        ], $sid))['data'];
        $lateA = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 6,
            'used_at' => '2026-08-20T12:00:00Z',
            'syringe_id' => $alt['id'],
        ], $sid))['data'];
        $lateB = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 7,
            'used_at' => '2026-08-20T12:00:00Z',
        ], $sid))['data'];

        $listed = $this->json($this->authed($app, 'GET', '/api/v1/uses', $sid))['data'];
        $this->assertTrue($this->isNewestFirst($listed));
        $ids = array_column($listed, 'id');
        $this->assertContains($early['id'], $ids);

        $limited = $this->json($this->authed($app, 'GET', '/api/v1/uses', $sid, 'limit=1'))['data'];
        $this->assertCount(1, $limited);

        $before = $this->json($this->authed($app, 'GET', '/api/v1/uses', $sid, 'before=2026-08-20T12:00:00Z'))['data'];
        $this->assertSame([$early['id']], array_column($before, 'id'));

        $capped = $this->json($this->authed($app, 'GET', '/api/v1/uses', $sid, 'limit=500'))['data'];
        $this->assertLessThanOrEqual(100, count($capped));
        $this->assertCount(3, $capped);

        $badLimit = $this->authed($app, 'GET', '/api/v1/uses', $sid, 'limit=0');
        $this->assertSame(422, $badLimit->getStatusCode());
        $badBefore = $this->authed($app, 'GET', '/api/v1/uses', $sid, 'before=nope');
        $this->assertSame(422, $badBefore->getStatusCode());

        $view = $this->json($this->authed($app, 'GET', '/api/v1/uses/' . $lateA['id'], $sid))['data'];
        $this->assertSame($lateA['id'], $view['id']);
        $this->assertSame('alt', $view['syringe_label']);

        $implicitSyringe = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 1,
            'used_at' => '2026-08-20T18:00:00Z',
        ], $sid))['data'];
        $this->assertSame($lateB['syringe_id'], $implicitSyringe['syringe_id']);

        $unknownUse = $this->authed($app, 'GET', '/api/v1/uses/missing', $sid);
        $this->assertSame(404, $unknownUse->getStatusCode());
        $unknownCompound = $this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 1,
            'compound_id' => 'missing',
        ], $sid);
        $this->assertSame(422, $unknownCompound->getStatusCode());

        $switched = $this->authedJson($app, 'PATCH', '/api/v1/uses/' . $lateA['id'], [
            'syringe_id' => $alt['id'],
            'used_at' => '2026-08-20T12:30:00Z',
        ], $sid);
        $this->assertSame(200, $switched->getStatusCode());
        $this->assertSame($alt['id'], $this->json($switched)['data']['syringe_id']);

        $emptySyringe = $this->authedJson($app, 'PATCH', '/api/v1/uses/' . $lateA['id'], [
            'syringe_id' => '',
        ], $sid);
        $this->assertSame(422, $emptySyringe->getStatusCode());
    }

    public function testMixValidationUnknownPeptideAndNonU100Dose(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);

        $unknown = $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => 'insulin',
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'compounded_at' => '2026-08-20T12:00',
        ], $sid);
        $this->assertSame(422, $unknown->getStatusCode());
        $this->assertSame(['peptide_type_id' => [DoseConfig::PEPTIDE_UNKNOWN]], $this->json($unknown)['error']['fields']);

        $this->mixTirzepatide($app, $sid, '2026-08-20T12:00');
        $u40 = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 1,
            'capacity_iu' => 40,
            'is_default' => true,
        ], $sid))['data'];
        $use = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 25,
            'syringe_id' => $u40['id'],
        ], $sid))['data'];
        $this->assertEqualsWithDelta(3.125, $use['peptide_mg'], 1e-9);
        $this->assertEqualsWithDelta(0.625, $use['volume_ml'], 1e-9);

        $twoDecimals = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 1.25], $sid);
        $this->assertSame(422, $twoDecimals->getStatusCode());
        $this->assertSame(['iu' => [DoseConfig::IU_ONE_DECIMAL]], $this->json($twoDecimals)['error']['fields']);
    }

    /**
     * @param list<array<string, mixed>> $uses
     */
    private function isNewestFirst(array $uses): bool
    {
        for ($i = 1, $n = count($uses); $i < $n; $i++) {
            $prev = (string) $uses[$i - 1]['used_at'];
            $next = (string) $uses[$i]['used_at'];
            if ($prev < $next) {
                return false;
            }
            if ($prev === $next && (string) $uses[$i - 1]['id'] < (string) $uses[$i]['id']) {
                return false;
            }
        }

        return $uses !== [];
    }

    private function mixTirzepatide(
        \Slim\App $app,
        string $sid,
        string $compoundedAt,
        float $mg = 10.0,
        float $bac = 2.0,
    ): \Psr\Http\Message\ResponseInterface {
        return $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => 'tirzepatide',
            'peptide_mg' => $mg,
            'bac_water_ml' => $bac,
            'compounded_at' => $compoundedAt,
        ], $sid);
    }

    private function register(\Slim\App $app, string $email = 'dose@example.com'): string
    {
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(201, $response->getStatusCode());

        return $this->sessionIdFrom($response);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function authedJson(
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

    private function authed(
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
