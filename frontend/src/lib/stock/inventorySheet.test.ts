import { describe, expect, it } from 'vitest';
import { isOpenStock, openStockOnly, sheetShowsArchivedId } from './inventorySheet';

const open = { id: 'open-1', archived_at: null };
const archived = { id: 'arch-1', archived_at: '2026-09-01T12:00:00Z' };

describe('inventory sheet presenter', () => {
  it('treats null archived_at as open', () => {
    expect(isOpenStock(open)).toBe(true);
    expect(isOpenStock(archived)).toBe(false);
  });

  it('keeps only open items on the sheet', () => {
    expect(openStockOnly([open, archived, { id: 'open-2', archived_at: null }]).map((item) => item.id)).toEqual([
      'open-1',
      'open-2'
    ]);
    expect(openStockOnly([archived])).toEqual([]);
    expect(openStockOnly([])).toEqual([]);
  });

  it('flags a sheet payload that still contains archived ids', () => {
    expect(sheetShowsArchivedId([open])).toBe(false);
    expect(sheetShowsArchivedId([open, archived])).toBe(true);
    expect(sheetShowsArchivedId([])).toBe(false);
  });
});
