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

    public function testCustomPeptideTypesCanBeAddedAndUsedToMix(): void
    {
        $app = $this->getAppInstance();
        $unauth = $app->handle($this->createRequest('POST', '/api/v1/peptide-types'));
        $this->assertSame(401, $unauth->getStatusCode());

        $sid = $this->register($app);
        $created = $this->authedJson($app, 'POST', '/api/v1/peptide-types', ['name' => 'Cagrilintide'], $sid);
        $this->assertSame(201, $created->getStatusCode());
        $row = $this->json($created)['data'];
        $this->assertSame('Cagrilintide', $row['name']);
        $this->assertSame('cagrilintide', $row['slug']);

        $names = array_map(static fn (array $item): string => $item['name'], $this->json($this->authed($app, 'GET', '/api/v1/peptide-types', $sid))['data']);
        $this->assertSame(['Semaglutide', 'Tirzepatide', 'Retatrutide', 'Liraglutide', 'Cagrilintide'], $names);

        $dup = $this->authedJson($app, 'POST', '/api/v1/peptide-types', ['name' => 'Tirzepatide'], $sid);
        $this->assertSame(422, $dup->getStatusCode());
        $this->assertSame(['name' => [DoseConfig::PEPTIDE_NAME_TAKEN]], $this->json($dup)['error']['fields']);

        $blank = $this->authedJson($app, 'POST', '/api/v1/peptide-types', ['name' => '  '], $sid);
        $this->assertSame(422, $blank->getStatusCode());
        $this->assertSame(['name' => [DoseConfig::MUST_BE_TEXT]], $this->json($blank)['error']['fields']);

        $this->ensureBac($app, $sid, 2.0);
        $mixed = $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => $row['id'],
            'peptide_mg' => 10.0,
            'bac_water_ml' => 2.0,
            'compounded_at' => '2026-08-20T12:00',
        ], $sid);
        $this->assertSame(201, $mixed->getStatusCode());
        $this->assertSame('Cagrilintide', $this->json($mixed)['data']['peptide_type_name']);
        $this->assertSame('cagrilintide', $this->json($mixed)['data']['peptide_type_slug']);
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

    public function testDeleteUseRestoresRemainderAndMissingIs404(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $this->mixTirzepatide($app, $sid, '2026-08-20T12:00');
        $use = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 25], $sid))['data'];

        $deleted = $this->authed($app, 'DELETE', '/api/v1/uses/' . $use['id'], $sid);
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/uses/' . $use['id'], $sid)->getStatusCode());

        $current = $this->json($this->authed($app, 'GET', '/api/v1/compounds/current', $sid))['data'];
        $this->assertEqualsWithDelta(10.0, $current['remaining_mg'], 1e-9);
        $this->assertFalse($current['has_uses']);

        $missing = $this->authed($app, 'DELETE', '/api/v1/uses/not-a-real-id', $sid);
        $this->assertSame(404, $missing->getStatusCode());
    }

    public function testCompoundStaysEditableAfterUseAndDeleteRequiresNoUses(): void
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

        $logged = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 25], $sid))['data'];
        $this->assertEqualsWithDelta(1.25, $logged['peptide_mg'], 1e-9);

        $afterUse = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $id, [
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'peptide_type_id' => 'tirzepatide',
            'notes' => 'after use',
            'compounded_at' => '2026-08-21T08:00',
        ], $sid);
        $this->assertSame(200, $afterUse->getStatusCode());
        $patched = $this->json($afterUse)['data'];
        $this->assertSame('Tirzepatide', $patched['peptide_type_name']);
        $this->assertEqualsWithDelta(10.0, $patched['peptide_mg'], 1e-9);
        $this->assertSame('after use', $patched['notes']);
        $this->assertSame('2026-08-21T08:00', $patched['compounded_at']);
        $this->assertTrue($patched['has_uses']);

        $use = $this->json($this->authed($app, 'GET', '/api/v1/uses/' . $logged['id'], $sid))['data'];
        $this->assertEqualsWithDelta(1.25, $use['peptide_mg'], 1e-9);
        $this->assertSame('Tirzepatide', $use['peptide_type_name']);

        $over = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $id, [
            'bac_water_ml' => 0.2,
        ], $sid);
        $this->assertSame(422, $over->getStatusCode());
        $this->assertSame(
            ['peptide_mg' => [DoseConfig::COMPOUND_OVERDRAW]],
            $this->json($over)['error']['fields']
        );

        $blocked = $this->authed($app, 'DELETE', '/api/v1/compounds/' . $id, $sid);
        $this->assertSame(422, $blocked->getStatusCode());
        $this->assertSame(DoseConfig::COMPOUND_HAS_USES, $this->json($blocked)['error']['description']);
        $this->assertSame(
            ['id' => [DoseConfig::COMPOUND_HAS_USES]],
            $this->json($blocked)['error']['fields']
        );

        $fresh = $this->json($this->mixTirzepatide($app, $sid, '2026-08-22T09:00'))['data']['id'];
        $deleted = $this->authed($app, 'DELETE', '/api/v1/compounds/' . $fresh, $sid);
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/compounds/' . $fresh, $sid)->getStatusCode());

        $missing = $this->authed($app, 'DELETE', '/api/v1/compounds/not-a-real-id', $sid);
        $this->assertSame(404, $missing->getStatusCode());

        $view = $this->json($this->authed($app, 'GET', '/api/v1/compounds/' . $id, $sid))['data'];
        $this->assertSame($id, $view['id']);
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/compounds/not-a-real-id', $sid)->getStatusCode());
    }

    public function testSyringeCreateDefaultUniquenessAndAutoLabel(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);

        $list = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'];
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['is_default']);
        $this->assertSame(50, $list[0]['quantity']);
        $seededId = $list[0]['id'];

        $created = $this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 1,
            'capacity_iu' => 40,
        ], $sid);
        $this->assertSame(201, $created->getStatusCode());
        $new = $this->json($created)['data'];
        $this->assertSame('1 mL / 40 IU', $new['label']);
        $this->assertFalse($new['is_default']);
        $this->assertSame(0, $new['quantity']);

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

        $viewed = $this->json($this->authed($app, 'GET', '/api/v1/syringes/' . $new['id'], $sid))['data'];
        $this->assertSame('U-40', $viewed['label']);
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/syringes/missing', $sid)->getStatusCode());

        $resized = $this->json($this->authedJson($app, 'PATCH', '/api/v1/syringes/' . $seededId, [
            'volume_ml' => 1,
            'capacity_iu' => 100,
        ], $sid))['data'];
        $this->assertSame('1 mL / 100 IU', $resized['label']);
        $this->assertEqualsWithDelta(1.0, $resized['volume_ml'], 1e-9);

        $unset = $this->authedJson($app, 'PATCH', '/api/v1/syringes/' . $new['id'], [
            'is_default' => false,
        ], $sid);
        $this->assertSame(422, $unset->getStatusCode());
        $this->assertSame(['is_default' => [DoseConfig::DEFAULT_REQUIRED]], $this->json($unset)['error']['fields']);

        $createDefault = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 0.3,
            'capacity_iu' => 30,
            'is_default' => true,
            'quantity' => 7,
        ], $sid))['data'];
        $this->assertTrue($createDefault['is_default']);
        $this->assertSame(7, $createDefault['quantity']);
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

        $deleted = $this->authed($app, 'DELETE', '/api/v1/syringes/' . $new['id'], $sid);
        $this->assertSame(204, $deleted->getStatusCode());
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/syringes/' . $new['id'], $sid)->getStatusCode());
        $this->assertSame(204, $this->authed($app, 'DELETE', '/api/v1/syringes/' . $createDefault['id'], $sid)->getStatusCode());

        $last = $this->authed($app, 'DELETE', '/api/v1/syringes/' . $seededId, $sid);
        $this->assertSame(422, $last->getStatusCode());
        $this->assertSame(['id' => [DoseConfig::SYRINGE_LAST]], $this->json($last)['error']['fields']);
        $this->assertSame(404, $this->authed($app, 'DELETE', '/api/v1/syringes/missing', $sid)->getStatusCode());
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
            'quantity' => 20,
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
        $this->assertSame(200, $emptySyringe->getStatusCode());
        $this->assertNull($this->json($emptySyringe)['data']['syringe_id']);
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
            'quantity' => 20,
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

    public function testBacBottlesBurnOnMixAndRefundOnDelete(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app, restockSyringes: false);

        $unauth = $app->handle($this->createRequest('GET', '/api/v1/bac-bottles'));
        $this->assertSame(401, $unauth->getStatusCode());

        $missingCurrent = $this->authed($app, 'GET', '/api/v1/bac-bottles/current', $sid);
        $this->assertSame(404, $missingCurrent->getStatusCode());
        $this->assertSame([], $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles', $sid))['data']);

        $created = $this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => 10,
            'opened_at' => '2026-08-19T08:00',
            'notes' => 'first',
        ], $sid);
        $this->assertSame(201, $created->getStatusCode());
        $bottle = $this->json($created)['data'];
        $this->assertEqualsWithDelta(10.0, $bottle['volume_ml'], 1e-9);
        $this->assertEqualsWithDelta(10.0, $bottle['remaining_ml'], 1e-9);
        $this->assertTrue($bottle['is_current']);
        $this->assertSame('first', $bottle['notes']);

        $view = $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $bottle['id'], $sid))['data'];
        $this->assertSame($bottle['id'], $view['id']);
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/bac-bottles/missing', $sid)->getStatusCode());

        $patched = $this->json($this->authedJson($app, 'PATCH', '/api/v1/bac-bottles/' . $bottle['id'], [
            'notes' => 'opened',
            'opened_at' => '2026-08-19T09:00',
        ], $sid))['data'];
        $this->assertSame('opened', $patched['notes']);
        $this->assertSame('2026-08-19T09:00', $patched['opened_at']);
        $notesOnly = $this->json($this->authedJson($app, 'PATCH', '/api/v1/bac-bottles/' . $bottle['id'], [
            'notes' => 'still',
        ], $sid))['data'];
        $this->assertSame('still', $notesOnly['notes']);
        $this->assertSame('2026-08-19T09:00', $notesOnly['opened_at']);

        $mixed = $this->json($this->mixTirzepatide($app, $sid, '2026-08-20T12:00'))['data'];
        $this->assertSame($bottle['id'], $mixed['bac_bottle_id']);
        $afterMix = $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/current', $sid))['data'];
        $this->assertEqualsWithDelta(8.0, $afterMix['remaining_ml'], 1e-9);

        $over = $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => 'tirzepatide',
            'peptide_mg' => 10,
            'bac_water_ml' => 9,
            'compounded_at' => '2026-08-21T12:00',
        ], $sid);
        $this->assertSame(422, $over->getStatusCode());
        $this->assertSame(
            DoseConfig::bacOverdraw('9', '8'),
            $this->json($over)['error']['fields']['bac_water_ml'][0]
        );

        $usedDelete = $this->authed($app, 'DELETE', '/api/v1/bac-bottles/' . $bottle['id'], $sid);
        $this->assertSame(422, $usedDelete->getStatusCode());
        $this->assertSame(DoseConfig::BAC_IN_USE, $this->json($usedDelete)['error']['description']);

        $this->authed($app, 'DELETE', '/api/v1/compounds/' . $mixed['id'], $sid);
        $refunded = $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $bottle['id'], $sid))['data'];
        $this->assertEqualsWithDelta(10.0, $refunded['remaining_ml'], 1e-9);

        $deleted = $this->authed($app, 'DELETE', '/api/v1/bac-bottles/' . $bottle['id'], $sid);
        $this->assertSame(204, $deleted->getStatusCode());
        $plain = $this->json($this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => 30,
        ], $sid))['data'];
        $this->assertEqualsWithDelta(30.0, $plain['remaining_ml'], 1e-9);
        $this->assertSame(204, $this->authed($app, 'DELETE', '/api/v1/bac-bottles/' . $plain['id'], $sid)->getStatusCode());
        $this->assertSame(404, $this->authed($app, 'GET', '/api/v1/bac-bottles/' . $bottle['id'], $sid)->getStatusCode());
        $this->assertSame(404, $this->authed($app, 'DELETE', '/api/v1/bac-bottles/missing', $sid)->getStatusCode());
    }

    public function testMixAndLogDoNotRequireBacOrSyringeStock(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app, restockSyringes: false);

        $mixed = $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => 'tirzepatide',
            'peptide_mg' => 10,
            'bac_water_ml' => 2,
            'compounded_at' => '2026-08-20T12:00',
        ], $sid);
        $this->assertSame(201, $mixed->getStatusCode());
        $this->assertNull($this->json($mixed)['data']['bac_bottle_id']);

        $syringeId = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'][0]['id'];
        $this->assertSame(0, $this->syringeQuantity($app, $sid, $syringeId));

        $use = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 10], $sid);
        $this->assertSame(201, $use->getStatusCode());
        $this->assertSame(0, $this->syringeQuantity($app, $sid, $syringeId));

        $withoutSyringe = $this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 5,
            'syringe_id' => null,
        ], $sid);
        $this->assertSame(201, $withoutSyringe->getStatusCode());
        $this->assertNull($this->json($withoutSyringe)['data']['syringe_id']);
        $this->assertEqualsWithDelta(0.5, $this->json($withoutSyringe)['data']['syringe_volume_ml'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $this->json($withoutSyringe)['data']['syringe_capacity_iu'], 1e-9);
    }

    public function testBacBottleBurnIsIndependentOfUses(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app, restockSyringes: false);
        $bottle = $this->json($this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => 10,
        ], $sid))['data'];

        $burned = $this->json($this->authedJson($app, 'POST', '/api/v1/bac-bottles/' . $bottle['id'] . '/burn', [
            'ml' => 2.5,
        ], $sid))['data'];
        $this->assertEqualsWithDelta(7.5, $burned['remaining_ml'], 1e-9);

        $over = $this->authedJson($app, 'POST', '/api/v1/bac-bottles/' . $bottle['id'] . '/burn', [
            'ml' => 20,
        ], $sid);
        $this->assertSame(422, $over->getStatusCode());
        $this->assertArrayHasKey('ml', $this->json($over)['error']['fields']);

        $this->assertSame(404, $this->authedJson($app, 'POST', '/api/v1/bac-bottles/missing/burn', [
            'ml' => 1,
        ], $sid)->getStatusCode());
    }

    public function testBacMixPatchAdjustsBottleAndNewerBottleBecomesCurrent(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app, restockSyringes: false);
        $first = $this->json($this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => 5,
            'opened_at' => '2026-08-18T08:00',
        ], $sid))['data'];
        $compound = $this->json($this->mixTirzepatide($app, $sid, '2026-08-20T12:00', 10.0, 2.0))['data'];
        $this->assertEqualsWithDelta(
            3.0,
            $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $first['id'], $sid))['data']['remaining_ml'],
            1e-9
        );

        $more = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $compound['id'], [
            'bac_water_ml' => 4,
        ], $sid);
        $this->assertSame(200, $more->getStatusCode());
        $this->assertEqualsWithDelta(
            1.0,
            $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $first['id'], $sid))['data']['remaining_ml'],
            1e-9
        );

        $tooMuch = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $compound['id'], [
            'bac_water_ml' => 8,
        ], $sid);
        $this->assertSame(422, $tooMuch->getStatusCode());

        $less = $this->authedJson($app, 'PATCH', '/api/v1/compounds/' . $compound['id'], [
            'bac_water_ml' => 2,
        ], $sid);
        $this->assertSame(200, $less->getStatusCode());
        $this->assertEqualsWithDelta(
            3.0,
            $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $first['id'], $sid))['data']['remaining_ml'],
            1e-9
        );

        $second = $this->json($this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => 10,
            'opened_at' => '2026-08-21T08:00',
        ], $sid))['data'];
        $listed = $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles', $sid))['data'];
        $this->assertSame($second['id'], $listed[0]['id']);
        $this->assertTrue($listed[0]['is_current']);
        $this->assertFalse($listed[1]['is_current']);

        $this->mixTirzepatide($app, $sid, '2026-08-21T12:00', 10.0, 2.0);
        $this->assertEqualsWithDelta(
            8.0,
            $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $second['id'], $sid))['data']['remaining_ml'],
            1e-9
        );
        $this->assertEqualsWithDelta(
            3.0,
            $this->json($this->authed($app, 'GET', '/api/v1/bac-bottles/' . $first['id'], $sid))['data']['remaining_ml'],
            1e-9
        );
    }

    public function testSyringeStockRestockBurnLogAndManualBurn(): void
    {
        $app = $this->getAppInstance();
        $sid = $this->register($app);
        $syringeId = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'][0]['id'];
        $this->assertSame(50, $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'][0]['quantity']);

        $restocked = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes/' . $syringeId . '/restock', [
            'count' => 10,
        ], $sid))['data'];
        $this->assertSame(60, $restocked['quantity']);

        $burned = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes/' . $syringeId . '/burn', [
            'count' => 5,
        ], $sid))['data'];
        $this->assertSame(55, $burned['quantity']);

        $overBurn = $this->authedJson($app, 'POST', '/api/v1/syringes/' . $syringeId . '/burn', [
            'count' => 100,
        ], $sid);
        $this->assertSame(422, $overBurn->getStatusCode());
        $this->assertSame(
            DoseConfig::syringeOverdraw(100, 55),
            $this->json($overBurn)['error']['fields']['count'][0]
        );

        $this->mixTirzepatide($app, $sid, '2026-08-20T12:00');
        $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 10], $sid);
        $this->assertSame(55, $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'][0]['quantity']);

        $alt = $this->json($this->authedJson($app, 'POST', '/api/v1/syringes', [
            'volume_ml' => 1,
            'capacity_iu' => 40,
            'quantity' => 3,
        ], $sid))['data'];
        $use = $this->json($this->authedJson($app, 'POST', '/api/v1/uses', [
            'iu' => 10,
            'syringe_id' => $alt['id'],
        ], $sid))['data'];
        $this->assertSame(3, $this->syringeQuantity($app, $sid, $alt['id']));
        $this->assertSame(55, $this->syringeQuantity($app, $sid, $syringeId));

        $this->authedJson($app, 'PATCH', '/api/v1/uses/' . $use['id'], [
            'syringe_id' => $syringeId,
        ], $sid);
        $this->assertSame(3, $this->syringeQuantity($app, $sid, $alt['id']));
        $this->assertSame(55, $this->syringeQuantity($app, $sid, $syringeId));

        $this->authed($app, 'DELETE', '/api/v1/uses/' . $use['id'], $sid);
        $this->assertSame(55, $this->syringeQuantity($app, $sid, $syringeId));

        $this->authedJson($app, 'POST', '/api/v1/syringes/' . $syringeId . '/burn', ['count' => 55], $sid);
        $empty = $this->authedJson($app, 'POST', '/api/v1/uses', ['iu' => 5], $sid);
        $this->assertSame(201, $empty->getStatusCode());
        $this->assertSame(0, $this->syringeQuantity($app, $sid, $syringeId));

        $missing = $this->authedJson($app, 'POST', '/api/v1/syringes/missing/restock', ['count' => 1], $sid);
        $this->assertSame(404, $missing->getStatusCode());
        $missingBurn = $this->authedJson($app, 'POST', '/api/v1/syringes/missing/burn', ['count' => 1], $sid);
        $this->assertSame(404, $missingBurn->getStatusCode());
        $badCount = $this->authedJson($app, 'POST', '/api/v1/syringes/' . $syringeId . '/restock', ['count' => 0], $sid);
        $this->assertSame(422, $badCount->getStatusCode());
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
        $this->ensureBac($app, $sid, $bac);

        return $this->authedJson($app, 'POST', '/api/v1/compounds', [
            'peptide_type_id' => 'tirzepatide',
            'peptide_mg' => $mg,
            'bac_water_ml' => $bac,
            'compounded_at' => $compoundedAt,
        ], $sid);
    }

    private function ensureBac(\Slim\App $app, string $sid, float $neededMl): void
    {
        $current = $this->authed($app, 'GET', '/api/v1/bac-bottles/current', $sid);
        if ($current->getStatusCode() === 200) {
            $remaining = (float) $this->json($current)['data']['remaining_ml'];
            if ($remaining + 1e-9 >= $neededMl) {
                return;
            }
        }

        $this->authedJson($app, 'POST', '/api/v1/bac-bottles', [
            'volume_ml' => max(30.0, $neededMl),
            'opened_at' => '2026-08-01T00:00',
        ], $sid);
    }

    private function syringeQuantity(\Slim\App $app, string $sid, string $id): int
    {
        foreach ($this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'] as $row) {
            if ((string) $row['id'] === $id) {
                return (int) $row['quantity'];
            }
        }
        $this->fail('syringe not found');
    }

    private function register(
        \Slim\App $app,
        string $email = 'dose@example.com',
        bool $restockSyringes = true,
    ): string {
        $response = $app->handle($this->createJsonRequest('POST', '/api/v1/auth/register', [
            'email' => $email,
            'password' => 'twelvechars!!',
        ]));
        $this->assertSame(201, $response->getStatusCode());
        $sid = $this->sessionIdFrom($response);
        if ($restockSyringes) {
            $id = $this->json($this->authed($app, 'GET', '/api/v1/syringes', $sid))['data'][0]['id'];
            $this->authedJson($app, 'POST', '/api/v1/syringes/' . $id . '/restock', ['count' => 50], $sid);
        }

        return $sid;
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
