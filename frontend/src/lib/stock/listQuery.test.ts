import { describe, expect, it } from 'vitest';
import { inventoryListPath } from './listQuery';

describe('inventory list query', () => {
  it('omits the query for the default open-only list', () => {
    expect(inventoryListPath('compounds')).toBe('/api/v1/compounds');
    expect(inventoryListPath('bac-bottles')).toBe('/api/v1/bac-bottles');
    expect(inventoryListPath('syringes')).toBe('/api/v1/syringes');
  });

  it('appends view=all only for that exact flag', () => {
    expect(inventoryListPath('compounds', 'all')).toBe('/api/v1/compounds?view=all');
    expect(inventoryListPath('bac-bottles', 'all')).toBe('/api/v1/bac-bottles?view=all');
    expect(inventoryListPath('syringes', 'all')).toBe('/api/v1/syringes?view=all');
    expect(inventoryListPath('syringes', 'open' as 'all')).toBe('/api/v1/syringes');
  });
});
