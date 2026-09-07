import { describe, expect, it } from 'vitest';
import { groupStock, syringeDetailHref, vialDetailHref, waterDetailHref } from './inventoryFull';

describe('inventory full-list grouping', () => {
  it('splits open then archived without mixing ids', () => {
    const items = [
      { id: 'a', archived_at: null },
      { id: 'b', archived_at: '2026-09-01T00:00:00Z' },
      { id: 'c', archived_at: null }
    ];
    expect(groupStock(items)).toEqual({
      open: [
        { id: 'a', archived_at: null },
        { id: 'c', archived_at: null }
      ],
      archived: [{ id: 'b', archived_at: '2026-09-01T00:00:00Z' }]
    });
  });

  it('returns empty groups when nothing is present', () => {
    expect(groupStock([])).toEqual({ open: [], archived: [] });
  });

  it('builds existing edit routes for each kind', () => {
    expect(vialDetailHref('c1')).toBe('/inventory/c1');
    expect(waterDetailHref('b1')).toBe('/inventory/water/b1');
    expect(syringeDetailHref('s1')).toBe('/inventory/syringes/s1');
  });
});
