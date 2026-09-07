import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  defaultOpenVialId,
  defaultSyringeId,
  deleteCompound,
  deleteBacBottle,
  fetchBacBottles,
  fetchBacBottle,
  fetchCompound,
  fetchCompounds,
  fetchCurrentBacBottle,
  fetchCurrentCompound,
  fetchOpenCompounds,
  fetchPeptideTypes,
  fetchSyringe,
  fetchSyringes,
  fetchUse,
  fetchUses,
  logUse,
  mixCompound,
  addBacBottle,
  createPeptideType,
  createSyringe,
  patchBacBottle,
  patchCompound,
  patchSyringe,
  patchUse,
  deleteUse,
  deleteSyringe,
  parseCountInput,
  remainingIuFrom,
  restockSyringe,
  vialLabel,
  burnSyringe,
  burnBacBottle,
  archiveBacBottle,
  archiveCompound,
  adjustCompound,
  fetchProfiles,
  createProfile,
  patchProfile,
  deleteProfile,
  vialsForProfile,
  PROFILE_MAX,
  type Compound
} from './inventory';

function jsonResponse(status: number, body: unknown) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body
  };
}

describe('inventory API client', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('lists catalogs and treats 404 current as empty', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [{ id: 'tirzepatide', slug: 'tirzepatide', name: 'Tirzepatide', sort_order: 2 }] }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [{ id: 's1', label: '0.5 mL / 50 IU', volume_ml: 0.5, capacity_iu: 50, is_default: true, quantity: 12 }] }))
        .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND', description: 'Syringe not found.' } }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [] }))
        .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND', description: 'Compound not found.' } }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }))
    );

    await expect(fetchPeptideTypes()).resolves.toHaveLength(1);
    await expect(fetchSyringes()).resolves.toHaveLength(1);
    await expect(fetchSyringe('missing')).resolves.toBeNull();
    await expect(fetchCompounds()).resolves.toEqual([]);
    await expect(fetchCurrentCompound()).resolves.toBeNull();
    await expect(fetchCurrentCompound()).resolves.toBeNull();
  });

  it('lists open vials', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(
          jsonResponse(200, {
            statusCode: 200,
            data: [{ id: 'c1', name: 'Fridge A', is_open: true, peptide_type_name: 'Tirzepatide' }]
          })
        )
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }))
    );
    await expect(fetchOpenCompounds()).resolves.toEqual([
      { id: 'c1', name: 'Fridge A', is_open: true, peptide_type_name: 'Tirzepatide' }
    ]);
    await expect(fetchOpenCompounds()).resolves.toEqual([]);
  });

  it('builds use list query strings and maps 404 use', async () => {
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [{ id: 'u1' }] }))
      .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } }))
      .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: { id: 'u1' } }))
      .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }));
    vi.stubGlobal('fetch', fetchMock);

    await expect(fetchUses({ limit: 5, before: '2026-08-20T12:00:00Z' })).resolves.toEqual([{ id: 'u1' }]);
    expect(String(fetchMock.mock.calls[0][0])).toContain('/api/v1/uses?limit=5&before=2026-08-20T12%3A00%3A00Z');
    await expect(fetchUse('missing')).resolves.toBeNull();
    await expect(fetchUse('u1')).resolves.toMatchObject({ id: 'u1' });
    await expect(fetchUses()).resolves.toEqual([]);
  });

  it('returns remaining_iu on 422 overdraw and succeeds on mix/log/patch', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: '25 IU exceeds 18 IU remaining in this vial.',
            fields: { iu: ['25 IU exceeds 18 IU remaining in this vial.'] },
            remaining_iu: 18
          }
        })
      )
    );
    const over = await logUse({ iu: 25 });
    expect(over.ok).toBe(false);
    if (!over.ok) {
      expect(over.remainingIu).toBe(18);
      expect(over.fields.iu[0]).toContain('remaining');
    }

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse(201, { statusCode: 201, data: { id: 'c1', peptide_type_name: 'Tirzepatide' } })
      )
    );
    await expect(
      mixCompound({
        peptide_type_id: 'tirzepatide',
        name: 'Fridge A',
        peptide_mg: 10,
        bac_water_ml: 2,
        compounded_at: '2026-08-20T12:00'
      })
    ).resolves.toMatchObject({ ok: true, status: 201 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(jsonResponse(200, { statusCode: 200, data: { id: 'u1', iu: 10 } }))
    );
    await expect(patchUse('u1', { iu: 10 })).resolves.toMatchObject({ ok: true });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Validation failed.',
            fields: { peptide_mg: ['Must be greater than 0.'] }
          }
        })
      )
    );
    await expect(
      mixCompound({
        peptide_type_id: 'tirzepatide',
        peptide_mg: 0,
        bac_water_ml: 2,
        compounded_at: '2026-08-20T12:00'
      })
    ).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('picks last-used syringe then default then first', () => {
    const syringes = [
      { id: 'a', label: 'A', volume_ml: 0.5, capacity_iu: 50, is_default: false, quantity: 4 },
      { id: 'b', label: 'B', volume_ml: 1, capacity_iu: 40, is_default: true, quantity: 8 }
    ];
    expect(defaultSyringeId(syringes, 'a')).toBe('a');
    expect(defaultSyringeId(syringes, 'missing')).toBe('b');
    expect(defaultSyringeId(syringes, null)).toBe('b');
    expect(defaultSyringeId([], null)).toBe('');
    expect(
      defaultSyringeId([{ id: 'z', label: 'Z', volume_ml: 1, capacity_iu: 1, is_default: false, quantity: 0 }], null)
    ).toBe('z');
  });

  it('labels vials by name and peptide and picks an open vial', () => {
    expect(vialLabel({ name: 'Tirzepatide', peptide_type_name: 'Tirzepatide' })).toBe('Tirzepatide');
    expect(vialLabel({ name: 'Fridge A', peptide_type_name: 'Tirzepatide' })).toBe('Fridge A · Tirzepatide');
    const vials = [
      { id: 'a', name: 'A', peptide_type_name: 'Tirzepatide' },
      { id: 'b', name: 'B', peptide_type_name: 'Semaglutide' }
    ] as const;
    expect(defaultOpenVialId([...vials], 'b')).toBe('b');
    expect(defaultOpenVialId([...vials], 'missing')).toBe('a');
    expect(defaultOpenVialId([], null)).toBe('');
  });

  it('reads remaining_iu only when finite', () => {
    expect(remainingIuFrom({ error: { remaining_iu: 175 } })).toBe(175);
    expect(remainingIuFrom({ error: { remaining_iu: '175' } })).toBeNull();
    expect(remainingIuFrom({})).toBeNull();
  });

  it('creates and patches syringes', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(201, {
          statusCode: 201,
          data: { id: 's2', label: '1 mL / 40 IU', volume_ml: 1, capacity_iu: 40, is_default: false, quantity: 0 }
        })
      )
    );
    await expect(createSyringe({ volume_ml: 1, capacity_iu: 40 })).resolves.toMatchObject({
      ok: true,
      status: 201
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, {
          statusCode: 200,
          data: { id: 's2', label: '1 mL / 40 IU', volume_ml: 1, capacity_iu: 40, is_default: true, quantity: 0 }
        })
      )
    );
    await expect(patchSyringe('s2', { is_default: true })).resolves.toMatchObject({ ok: true });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, {
          statusCode: 200,
          data: { id: 's2', label: '1 mL / 40 IU', volume_ml: 1, capacity_iu: 40, is_default: true, quantity: 0 }
        })
      )
    );
    await expect(fetchSyringe('s2')).resolves.toMatchObject({ id: 's2' });

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(204, { statusCode: 204 })));
    await expect(deleteSyringe('s2')).resolves.toMatchObject({ ok: true, status: 204 });
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Keep at least one syringe type.',
            fields: { id: ['Keep at least one syringe type.'] }
          }
        })
      )
    );
    await expect(deleteSyringe('s1')).resolves.toMatchObject({ ok: false, status: 422 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Validation failed.',
            fields: { volume_ml: ['Must be greater than 0.'] }
          }
        })
      )
    );
    await expect(createSyringe({ volume_ml: 0, capacity_iu: 50 })).resolves.toMatchObject({
      ok: false,
      status: 422
    });
  });

  it('creates a custom peptide type', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(201, {
          statusCode: 201,
          data: { id: 'p1', slug: 'cagrilintide', name: 'Cagrilintide', sort_order: 1000 }
        })
      )
    );
    await expect(createPeptideType({ name: 'Cagrilintide' })).resolves.toMatchObject({
      ok: true,
      status: 201,
      data: { name: 'Cagrilintide' }
    });
  });

  it('fetches, patches, and deletes a vial', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: { id: 'c1', peptide_mg: 10 } }))
        .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND' } }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }))
    );
    await expect(fetchCompound('c1')).resolves.toMatchObject({ id: 'c1' });
    await expect(fetchCompound('missing')).resolves.toBeNull();
    await expect(fetchCompound('empty')).resolves.toBeNull();

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'c1', peptide_mg: 12, notes: 'fixed' } })
      )
    );
    await expect(patchCompound('c1', { peptide_mg: 12, notes: 'fixed', name: 'Fridge A', is_open: false })).resolves.toMatchObject({
      ok: true,
      status: 200
    });

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(204, { statusCode: 204 })));
    await expect(deleteCompound('c1')).resolves.toMatchObject({ ok: true, status: 204 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'This vial has logged uses and cannot be deleted.',
            fields: { id: ['This vial has logged uses and cannot be deleted.'] }
          }
        })
      )
    );
    await expect(deleteCompound('c1')).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('deletes a use', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(204, { statusCode: 204 })));
    await expect(deleteUse('u1')).resolves.toMatchObject({ ok: true, status: 204 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(404, {
          statusCode: 404,
          error: { type: 'RESOURCE_NOT_FOUND', description: 'Use not found.' }
        })
      )
    );
    await expect(deleteUse('missing')).resolves.toMatchObject({ ok: false, status: 404 });
  });

  it('loads BAC bottles and maps 404 current as empty', async () => {
    vi.stubGlobal(
      'fetch',
      vi
        .fn()
        .mockResolvedValueOnce(
          jsonResponse(200, {
            statusCode: 200,
            data: [{ id: 'b1', volume_ml: 10, remaining_ml: 8, opened_at: '2026-08-20T12:00', notes: null, created_at: 'x', is_current: true }]
          })
        )
        .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND' } }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }))
    );
    await expect(fetchBacBottles()).resolves.toHaveLength(1);
    await expect(fetchCurrentBacBottle()).resolves.toBeNull();
    await expect(fetchCurrentBacBottle()).resolves.toBeNull();
  });

  it('adds and deletes BAC bottles', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(201, { statusCode: 201, data: { id: 'b1', volume_ml: 10, remaining_ml: 10, is_current: true } })
      )
    );
    await expect(addBacBottle({ volume_ml: 10, opened_at: '2026-08-20T12:00' })).resolves.toMatchObject({
      ok: true,
      status: 201
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'b1', volume_ml: 10, remaining_ml: 10, notes: 'ok' } })
      )
    );
    await expect(fetchBacBottle('b1')).resolves.toMatchObject({ id: 'b1' });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(404, { statusCode: 404 })));
    await expect(fetchBacBottle('missing')).resolves.toBeNull();

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'b1', notes: 'fridge' } })
      )
    );
    await expect(patchBacBottle('b1', { notes: 'fridge' })).resolves.toMatchObject({ ok: true });

    vi.stubGlobal('fetch', vi.fn().mockResolvedValueOnce(jsonResponse(204, { statusCode: 204 })));
    await expect(deleteBacBottle('b1')).resolves.toMatchObject({ ok: true, status: 204 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'This bottle has been used and cannot be deleted.',
            fields: { id: ['This bottle has been used and cannot be deleted.'] }
          }
        })
      )
    );
    await expect(deleteBacBottle('b1')).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('restocks and burns syringes and parses counts', async () => {
    expect(parseCountInput('3')).toBe(3);
    expect(parseCountInput(' 1 ')).toBe(1);
    expect(parseCountInput('0')).toBeNull();
    expect(parseCountInput('1.5')).toBeNull();
    expect(parseCountInput('')).toBeNull();
    expect(parseCountInput('nope')).toBeNull();

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 's1', quantity: 12 } })
      )
    );
    await expect(restockSyringe('s1', 10)).resolves.toMatchObject({ ok: true, status: 200 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 's1', quantity: 9 } })
      )
    );
    await expect(burnSyringe('s1', 3)).resolves.toMatchObject({ ok: true });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: '3 exceeds 0 syringes remaining.',
            fields: { count: ['3 exceeds 0 syringes remaining.'] }
          }
        })
      )
    );
    await expect(burnSyringe('s1', 3)).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('burns BAC independently of mixes', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'b1', remaining_ml: 7.5 } })
      )
    );
    await expect(burnBacBottle('b1', 2.5)).resolves.toMatchObject({ ok: true, status: 200 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: '3 mL exceeds 0 mL remaining in bacteriostatic water.',
            fields: { ml: ['3 mL exceeds 0 mL remaining in bacteriostatic water.'] }
          }
        })
      )
    );
    await expect(burnBacBottle('b1', 3)).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('adjusts remaining volume and archives empty inventory', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'c1', remaining_ml: 1.5, adjustment_mg: -1.25 } })
      )
    );
    await expect(adjustCompound('c1', { remaining_ml: 1.5 })).resolves.toMatchObject({ ok: true, status: 200 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Remaining cannot exceed the mix volume.',
            fields: { remaining_ml: ['Remaining cannot exceed the mix volume.'] }
          }
        })
      )
    );
    await expect(adjustCompound('c1', { remaining_ml: 3 })).resolves.toMatchObject({ ok: false, status: 422 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'c1', archived_at: '2026-08-27T16:00:00Z' } })
      )
    );
    await expect(archiveCompound('c1')).resolves.toMatchObject({ ok: true, status: 200 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(200, { statusCode: 200, data: { id: 'b1', archived_at: '2026-08-27T16:00:00Z' } })
      )
    );
    await expect(archiveBacBottle('b1')).resolves.toMatchObject({ ok: true, status: 200 });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValueOnce(
        jsonResponse(422, {
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Archive is available when remaining is 0.',
            fields: { id: ['Archive is available when remaining is 0.'] }
          }
        })
      )
    );
    await expect(archiveCompound('c1')).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('lists and mutates profiles and filters uses by profile', async () => {
    expect(PROFILE_MAX).toBe(5);
    const fetchMock = vi
      .fn()
      .mockResolvedValueOnce(
        jsonResponse(200, {
          statusCode: 200,
          data: [{ id: 'p1', name: 'Default', is_default: true, created_at: 'now' }]
        })
      )
      .mockResolvedValueOnce(
        jsonResponse(201, {
          statusCode: 201,
          data: { id: 'p2', name: 'Sam', is_default: false, created_at: 'now' }
        })
      )
      .mockResolvedValueOnce(
        jsonResponse(200, {
          statusCode: 200,
          data: { id: 'p2', name: 'Alex', is_default: false, created_at: 'now' }
        })
      )
      .mockResolvedValueOnce(jsonResponse(204, { statusCode: 204 }))
      .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [{ id: 'u1' }] }));
    vi.stubGlobal('fetch', fetchMock);

    await expect(fetchProfiles()).resolves.toEqual([
      { id: 'p1', name: 'Default', is_default: true, created_at: 'now' }
    ]);
    await expect(createProfile({ name: 'Sam' })).resolves.toMatchObject({ ok: true, status: 201 });
    await expect(patchProfile('p2', { name: 'Alex' })).resolves.toMatchObject({ ok: true });
    await expect(deleteProfile('p2')).resolves.toMatchObject({ ok: true, status: 204 });
    await expect(fetchUses({ profile_id: 'p1', limit: 10 })).resolves.toEqual([{ id: 'u1' }]);
    expect(String(fetchMock.mock.calls[4][0])).toContain('profile_id=p1');
    expect(vialsForProfile(
      [
        { id: 'c1', profile_ids: ['p1'] },
        { id: 'c2', profile_ids: ['p2'] }
      ] as Compound[],
      'p1'
    ).map((item) => item.id)).toEqual(['c1']);
    expect(vialsForProfile([{ id: 'c3' } as Compound], 'p1')).toEqual([]);
  });
});

const ORIGIN = 'http://app.test';
const JSON_HEADERS = { 'Content-Type': 'application/json' };

function stubPayload(status: number, body: unknown) {
  const fetchMock = vi.fn().mockResolvedValue(jsonResponse(status, body));
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

function expectFetch(
  fetchMock: ReturnType<typeof vi.fn>,
  url: string,
  init?: { method?: string; headers?: Record<string, string>; body?: unknown }
) {
  expect(fetchMock.mock.calls[0][0]).toBe(url);
  const actual = (fetchMock.mock.calls[0][1] ?? {}) as RequestInit;
  if (init?.method !== undefined) {
    expect(actual.method).toBe(init.method);
  }
  if (init?.headers !== undefined) {
    expect(actual.headers).toEqual(init.headers);
  }
  if (init?.body !== undefined) {
    expect(JSON.parse(String(actual.body))).toEqual(init.body);
  }
}

describe('inventory client contracts', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('keeps remaining_iu only for finite numbers', () => {
    expect(remainingIuFrom({ error: { remaining_iu: Number.POSITIVE_INFINITY } })).toBeNull();
    expect(remainingIuFrom({ error: { remaining_iu: Number.NaN } })).toBeNull();
    expect(remainingIuFrom({ error: { remaining_iu: 0 } })).toBe(0);
  });

  it('treats 404-with-body as missing and 200-with-data as present', async () => {
    const ghost = { id: 'ghost', label: 'gone' };
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchSyringe('ghost')).resolves.toBeNull();
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchCompound('ghost')).resolves.toBeNull();
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchBacBottle('ghost')).resolves.toBeNull();
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchUse('ghost')).resolves.toBeNull();
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchCurrentCompound()).resolves.toBeNull();
    stubPayload(404, { statusCode: 404, data: ghost, error: { type: 'RESOURCE_NOT_FOUND', description: 'missing' } });
    await expect(fetchCurrentBacBottle()).resolves.toBeNull();

    stubPayload(200, { statusCode: 200, data: { id: 'c1', name: 'Fridge' } });
    await expect(fetchCurrentCompound()).resolves.toMatchObject({ id: 'c1' });
    stubPayload(200, { statusCode: 200, data: { id: 'b1', volume_ml: 10 } });
    await expect(fetchCurrentBacBottle()).resolves.toMatchObject({ id: 'b1' });
  });

  it('requires 2xx plus a body for write success and uses validation copy', async () => {
    stubPayload(199, { statusCode: 199, data: { id: 'p1', name: 'X', slug: 'x', sort_order: 1 } });
    await expect(createPeptideType({ name: 'X' })).resolves.toMatchObject({ ok: false, status: 199 });
    stubPayload(200, { statusCode: 200 });
    await expect(createPeptideType({ name: 'X' })).resolves.toMatchObject({ ok: false, status: 200 });
    stubPayload(300, { statusCode: 300, data: { id: 'p1', name: 'X', slug: 'x', sort_order: 1 } });
    await expect(createPeptideType({ name: 'X' })).resolves.toMatchObject({ ok: false, status: 300 });
    stubPayload(200, { statusCode: 200, data: { id: 'p1', name: 'X', slug: 'x', sort_order: 1 } });
    await expect(createPeptideType({ name: 'X' })).resolves.toMatchObject({ ok: true, status: 200 });
    stubPayload(422, { statusCode: 422, error: { type: 'VALIDATION_ERROR', description: '' } });
    await expect(createPeptideType({ name: '' })).resolves.toMatchObject({
      ok: false,
      message: 'Check the highlighted fields.'
    });
  });

  it('accepts 200 deletes and rejects 199 or 300', async () => {
    stubPayload(200, { statusCode: 200 });
    await expect(deleteCompound('c1')).resolves.toMatchObject({ ok: true, status: 200 });
    stubPayload(199, { statusCode: 199 });
    await expect(deleteCompound('c1')).resolves.toMatchObject({ ok: false, status: 199 });
    stubPayload(300, { statusCode: 300 });
    await expect(deleteCompound('c1')).resolves.toMatchObject({ ok: false, status: 300 });
    stubPayload(200, { statusCode: 200 });
    await expect(deleteBacBottle('b1')).resolves.toMatchObject({ ok: true, status: 200 });
    stubPayload(199, { statusCode: 199 });
    await expect(deleteBacBottle('b1')).resolves.toMatchObject({ ok: false, status: 199 });
    stubPayload(200, { statusCode: 200 });
    await expect(deleteUse('u1')).resolves.toMatchObject({ ok: true, status: 200 });
    stubPayload(200, { statusCode: 200 });
    await expect(deleteSyringe('s1')).resolves.toMatchObject({ ok: true, status: 200 });
    stubPayload(200, { statusCode: 200 });
    await expect(deleteProfile('p1')).resolves.toMatchObject({ ok: true, status: 200 });
  });

  it('omits unused use-list query keys', async () => {
    const fetchMock = stubPayload(200, { statusCode: 200, data: [] });
    await expect(fetchUses()).resolves.toEqual([]);
    expect(fetchMock.mock.calls[0][0]).toBe('/api/v1/uses');
  });

  it('prefixes reads with the given origin and keeps default origin empty', async () => {
    const reads: Array<{ run: () => Promise<unknown>; prefixed: string; relative: string }> = [
      { run: () => fetchPeptideTypes(ORIGIN), prefixed: `${ORIGIN}/api/v1/peptide-types`, relative: '/api/v1/peptide-types' },
      { run: () => fetchSyringes(ORIGIN), prefixed: `${ORIGIN}/api/v1/syringes`, relative: '/api/v1/syringes' },
      { run: () => fetchSyringe('s1', ORIGIN), prefixed: `${ORIGIN}/api/v1/syringes/s1`, relative: '/api/v1/syringes/s1' },
      { run: () => fetchCompounds(ORIGIN), prefixed: `${ORIGIN}/api/v1/compounds`, relative: '/api/v1/compounds' },
      { run: () => fetchOpenCompounds(ORIGIN), prefixed: `${ORIGIN}/api/v1/compounds/open`, relative: '/api/v1/compounds/open' },
      { run: () => fetchCompound('c1', ORIGIN), prefixed: `${ORIGIN}/api/v1/compounds/c1`, relative: '/api/v1/compounds/c1' },
      { run: () => fetchCurrentCompound(ORIGIN), prefixed: `${ORIGIN}/api/v1/compounds/current`, relative: '/api/v1/compounds/current' },
      { run: () => fetchBacBottles(ORIGIN), prefixed: `${ORIGIN}/api/v1/bac-bottles`, relative: '/api/v1/bac-bottles' },
      { run: () => fetchBacBottle('b1', ORIGIN), prefixed: `${ORIGIN}/api/v1/bac-bottles/b1`, relative: '/api/v1/bac-bottles/b1' },
      { run: () => fetchCurrentBacBottle(ORIGIN), prefixed: `${ORIGIN}/api/v1/bac-bottles/current`, relative: '/api/v1/bac-bottles/current' },
      { run: () => fetchUse('u1', ORIGIN), prefixed: `${ORIGIN}/api/v1/uses/u1`, relative: '/api/v1/uses/u1' },
      { run: () => fetchUses({ baseUrl: ORIGIN }), prefixed: `${ORIGIN}/api/v1/uses`, relative: '/api/v1/uses' },
      { run: () => fetchProfiles(ORIGIN), prefixed: `${ORIGIN}/api/v1/profiles`, relative: '/api/v1/profiles' }
    ];

    for (const item of reads) {
      const prefixed = stubPayload(200, { statusCode: 200, data: [] });
      await item.run();
      expect(prefixed.mock.calls[0][0]).toBe(item.prefixed);
    }

    const relativeRuns: Array<{ run: () => Promise<unknown>; url: string }> = [
      { run: () => fetchPeptideTypes(), url: '/api/v1/peptide-types' },
      { run: () => fetchSyringes(), url: '/api/v1/syringes' },
      { run: () => fetchSyringe('s1'), url: '/api/v1/syringes/s1' },
      { run: () => fetchCompounds(), url: '/api/v1/compounds' },
      { run: () => fetchOpenCompounds(), url: '/api/v1/compounds/open' },
      { run: () => fetchCompound('c1'), url: '/api/v1/compounds/c1' },
      { run: () => fetchCurrentCompound(), url: '/api/v1/compounds/current' },
      { run: () => fetchBacBottles(), url: '/api/v1/bac-bottles' },
      { run: () => fetchBacBottle('b1'), url: '/api/v1/bac-bottles/b1' },
      { run: () => fetchCurrentBacBottle(), url: '/api/v1/bac-bottles/current' },
      { run: () => fetchUse('u1'), url: '/api/v1/uses/u1' },
      { run: () => fetchProfiles(), url: '/api/v1/profiles' }
    ];
    for (const item of relativeRuns) {
      const fetchMock = stubPayload(200, { statusCode: 200, data: [] });
      await item.run();
      expect(fetchMock.mock.calls[0][0]).toBe(item.url);
    }
  });

  it('posts and patches with exact paths, JSON bodies, and fallback copy', async () => {
    const writes: Array<{
      run: () => Promise<{ ok: boolean; message?: string }>;
      url: string;
      method: string;
      body?: unknown;
      fallback: string;
    }> = [
      {
        run: () => createPeptideType({ name: 'Cagri' }, ORIGIN),
        url: `${ORIGIN}/api/v1/peptide-types`,
        method: 'POST',
        body: { name: 'Cagri' },
        fallback: 'Unable to add peptide.'
      },
      {
        run: () => addBacBottle({ volume_ml: 10, opened_at: '2026-08-20T12:00', notes: 'fridge' }, ORIGIN),
        url: `${ORIGIN}/api/v1/bac-bottles`,
        method: 'POST',
        body: { volume_ml: 10, opened_at: '2026-08-20T12:00', notes: 'fridge' },
        fallback: 'Unable to add bacteriostatic water.'
      },
      {
        run: () => patchBacBottle('b1', { opened_at: '2026-08-21T12:00', notes: null }, ORIGIN),
        url: `${ORIGIN}/api/v1/bac-bottles/b1`,
        method: 'PATCH',
        body: { opened_at: '2026-08-21T12:00', notes: null },
        fallback: 'Unable to save bottle.'
      },
      {
        run: () => burnBacBottle('b1', 2.5, ORIGIN),
        url: `${ORIGIN}/api/v1/bac-bottles/b1/burn`,
        method: 'POST',
        body: { ml: 2.5 },
        fallback: 'Unable to burn bacteriostatic water.'
      },
      {
        run: () => archiveBacBottle('b1', ORIGIN),
        url: `${ORIGIN}/api/v1/bac-bottles/b1/archive`,
        method: 'POST',
        body: {},
        fallback: 'Unable to archive bottle.'
      },
      {
        run: () =>
          mixCompound(
            {
              peptide_type_id: 'tirzepatide',
              peptide_mg: 10,
              bac_water_ml: 2,
              compounded_at: '2026-08-20T12:00',
              name: 'Fridge A',
              is_open: true,
              notes: null,
              profile_ids: ['p1']
            },
            ORIGIN
          ),
        url: `${ORIGIN}/api/v1/compounds`,
        method: 'POST',
        body: {
          peptide_type_id: 'tirzepatide',
          peptide_mg: 10,
          bac_water_ml: 2,
          compounded_at: '2026-08-20T12:00',
          name: 'Fridge A',
          is_open: true,
          notes: null,
          profile_ids: ['p1']
        },
        fallback: 'Unable to mix vial.'
      },
      {
        run: () =>
          patchCompound(
            'c1',
            {
              peptide_type_id: 'sema',
              peptide_mg: 12,
              bac_water_ml: 2,
              compounded_at: '2026-08-21T12:00',
              name: 'Fridge B',
              is_open: false,
              notes: 'fixed',
              profile_ids: ['p2']
            },
            ORIGIN
          ),
        url: `${ORIGIN}/api/v1/compounds/c1`,
        method: 'PATCH',
        body: {
          peptide_type_id: 'sema',
          peptide_mg: 12,
          bac_water_ml: 2,
          compounded_at: '2026-08-21T12:00',
          name: 'Fridge B',
          is_open: false,
          notes: 'fixed',
          profile_ids: ['p2']
        },
        fallback: 'Unable to save vial.'
      },
      {
        run: () => adjustCompound('c1', { remaining_ml: 1.5, notes: 'lost' }, ORIGIN),
        url: `${ORIGIN}/api/v1/compounds/c1/adjust`,
        method: 'POST',
        body: { remaining_ml: 1.5, notes: 'lost' },
        fallback: 'Unable to adjust remaining volume.'
      },
      {
        run: () => archiveCompound('c1', ORIGIN),
        url: `${ORIGIN}/api/v1/compounds/c1/archive`,
        method: 'POST',
        body: {},
        fallback: 'Unable to archive vial.'
      },
      {
        run: () =>
          logUse(
            { iu: 10, syringe_id: 's1', used_at: '2026-08-20T12:00', notes: null, compound_id: 'c1', profile_id: 'p1' },
            ORIGIN
          ),
        url: `${ORIGIN}/api/v1/uses`,
        method: 'POST',
        body: { iu: 10, syringe_id: 's1', used_at: '2026-08-20T12:00', notes: null, compound_id: 'c1', profile_id: 'p1' },
        fallback: 'Unable to log use.'
      },
      {
        run: () => patchUse('u1', { iu: 8, syringe_id: null, used_at: '2026-08-21T12:00', notes: 'n', profile_id: 'p1' }, ORIGIN),
        url: `${ORIGIN}/api/v1/uses/u1`,
        method: 'PATCH',
        body: { iu: 8, syringe_id: null, used_at: '2026-08-21T12:00', notes: 'n', profile_id: 'p1' },
        fallback: 'Unable to save use.'
      },
      {
        run: () => createSyringe({ volume_ml: 1, capacity_iu: 40, label: '1 mL', is_default: false, quantity: 2 }, ORIGIN),
        url: `${ORIGIN}/api/v1/syringes`,
        method: 'POST',
        body: { volume_ml: 1, capacity_iu: 40, label: '1 mL', is_default: false, quantity: 2 },
        fallback: 'Unable to add syringe.'
      },
      {
        run: () => patchSyringe('s2', { label: 'edited', is_default: true, volume_ml: 1, capacity_iu: 40 }, ORIGIN),
        url: `${ORIGIN}/api/v1/syringes/s2`,
        method: 'PATCH',
        body: { label: 'edited', is_default: true, volume_ml: 1, capacity_iu: 40 },
        fallback: 'Unable to update syringe.'
      },
      {
        run: () => restockSyringe('s1', 4, ORIGIN),
        url: `${ORIGIN}/api/v1/syringes/s1/restock`,
        method: 'POST',
        body: { count: 4 },
        fallback: 'Unable to restock syringes.'
      },
      {
        run: () => burnSyringe('s1', 2, ORIGIN),
        url: `${ORIGIN}/api/v1/syringes/s1/burn`,
        method: 'POST',
        body: { count: 2 },
        fallback: 'Unable to burn syringes.'
      },
      {
        run: () => createProfile({ name: 'Sam' }, ORIGIN),
        url: `${ORIGIN}/api/v1/profiles`,
        method: 'POST',
        body: { name: 'Sam' },
        fallback: 'Unable to add profile.'
      },
      {
        run: () => patchProfile('p2', { name: 'Alex' }, ORIGIN),
        url: `${ORIGIN}/api/v1/profiles/p2`,
        method: 'PATCH',
        body: { name: 'Alex' },
        fallback: 'Unable to rename profile.'
      }
    ];

    for (const item of writes) {
      const fetchMock = stubPayload(500, { statusCode: 500, error: { type: 'SERVER_ERROR', description: '' } });
      const result = await item.run();
      expect(result).toMatchObject({ ok: false, message: item.fallback });
      expectFetch(fetchMock, item.url, { method: item.method, headers: JSON_HEADERS, body: item.body });
    }
  });

  it('deletes at the resource URL with a fallback message', async () => {
    const deletes: Array<{
      run: () => Promise<{ ok: boolean; message?: string }>;
      url: string;
      fallback: string;
    }> = [
      { run: () => deleteBacBottle('b1', ORIGIN), url: `${ORIGIN}/api/v1/bac-bottles/b1`, fallback: 'Unable to delete bottle.' },
      { run: () => deleteCompound('c1', ORIGIN), url: `${ORIGIN}/api/v1/compounds/c1`, fallback: 'Unable to delete vial.' },
      { run: () => deleteUse('u1', ORIGIN), url: `${ORIGIN}/api/v1/uses/u1`, fallback: 'Unable to delete use.' },
      { run: () => deleteSyringe('s1', ORIGIN), url: `${ORIGIN}/api/v1/syringes/s1`, fallback: 'Unable to delete syringe.' },
      { run: () => deleteProfile('p2', ORIGIN), url: `${ORIGIN}/api/v1/profiles/p2`, fallback: 'Unable to delete profile.' }
    ];
    for (const item of deletes) {
      const fetchMock = stubPayload(500, { statusCode: 500, error: { type: 'SERVER_ERROR', description: '' } });
      const result = await item.run();
      expect(result).toMatchObject({ ok: false, message: item.fallback });
      expectFetch(fetchMock, item.url, { method: 'DELETE' });
    }
  });
});
