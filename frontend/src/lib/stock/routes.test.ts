import { describe, expect, it } from 'vitest';
import { fullListHref, INVENTORY_FULL_LIST_ROUTES, isInventoryFullListPath } from './routes';

describe('inventory full-list routes', () => {
  it('names the three index paths', () => {
    expect(INVENTORY_FULL_LIST_ROUTES.map((route) => route.path)).toEqual([
      '/inventory/vials',
      '/inventory/water',
      '/inventory/syringes'
    ]);
    expect(INVENTORY_FULL_LIST_ROUTES.map((route) => route.title)).toEqual(['Vials', 'BAC', 'Syringes']);
  });

  it('detects only those exact indexes', () => {
    expect(isInventoryFullListPath('/inventory/vials')).toBe(true);
    expect(isInventoryFullListPath('/inventory/water')).toBe(true);
    expect(isInventoryFullListPath('/inventory/syringes')).toBe(true);
    expect(isInventoryFullListPath('/inventory/vials/extra')).toBe(false);
    expect(isInventoryFullListPath('/inventory')).toBe(false);
    expect(isInventoryFullListPath('/inventory/abc')).toBe(false);
  });

  it('resolves hrefs and falls back to the sheet', () => {
    expect(fullListHref('vials')).toBe('/inventory/vials');
    expect(fullListHref('water')).toBe('/inventory/water');
    expect(fullListHref('syringes')).toBe('/inventory/syringes');
    expect(fullListHref('unknown' as 'vials')).toBe('/inventory');
  });
});
