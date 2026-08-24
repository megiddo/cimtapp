import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  defaultSyringeId,
  deleteCompound,
  deleteBacBottle,
  fetchBacBottles,
  fetchBacBottle,
  fetchCompound,
  fetchCompounds,
  fetchCurrentBacBottle,
  fetchCurrentCompound,
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
  burnSyringe,
  burnBacBottle
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
    await expect(patchCompound('c1', { peptide_mg: 12, notes: 'fixed' })).resolves.toMatchObject({
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
});
