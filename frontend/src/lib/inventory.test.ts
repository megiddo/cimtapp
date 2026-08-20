import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  defaultSyringeId,
  fetchCompounds,
  fetchCurrentCompound,
  fetchPeptideTypes,
  fetchSyringes,
  fetchUse,
  fetchUses,
  logUse,
  mixCompound,
  patchUse,
  remainingIuFrom
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
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [{ id: 's1', label: '0.5 mL / 50 IU', volume_ml: 0.5, capacity_iu: 50, is_default: true }] }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200, data: [] }))
        .mockResolvedValueOnce(jsonResponse(404, { statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND', description: 'Compound not found.' } }))
        .mockResolvedValueOnce(jsonResponse(200, { statusCode: 200 }))
    );

    await expect(fetchPeptideTypes()).resolves.toHaveLength(1);
    await expect(fetchSyringes()).resolves.toHaveLength(1);
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
      { id: 'a', label: 'A', volume_ml: 0.5, capacity_iu: 50, is_default: false },
      { id: 'b', label: 'B', volume_ml: 1, capacity_iu: 40, is_default: true }
    ];
    expect(defaultSyringeId(syringes, 'a')).toBe('a');
    expect(defaultSyringeId(syringes, 'missing')).toBe('b');
    expect(defaultSyringeId(syringes, null)).toBe('b');
    expect(defaultSyringeId([], null)).toBe('');
    expect(defaultSyringeId([{ id: 'z', label: 'Z', volume_ml: 1, capacity_iu: 1, is_default: false }], null)).toBe(
      'z'
    );
  });

  it('reads remaining_iu only when finite', () => {
    expect(remainingIuFrom({ error: { remaining_iu: 175 } })).toBe(175);
    expect(remainingIuFrom({ error: { remaining_iu: '175' } })).toBeNull();
    expect(remainingIuFrom({})).toBeNull();
  });
});
