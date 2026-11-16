import { describe, expect, it } from 'vitest';
import { applyLogDefaults } from './logDefaults';

describe('applyLogDefaults', () => {
  it('keeps client date while copying IU and vial from defaults', () => {
    const seed = applyLogDefaults(
      {
        person_id: 'p1',
        logged_at: '2026-11-16T10:18',
        iu: 25,
        is_last_draw: false,
        vial_id: 'v1',
        probably_empty: false,
        source: { log_id: 'e1', logged_at: '2026-11-09T09:00' }
      },
      '2026-11-16T14:30'
    );
    expect(seed.loggedAt).toBe('2026-11-16T14:30');
    expect(seed.vialId).toBe('v1');
    expect(seed.iuText).toBe('25');
    expect(seed.sourceLoggedAt).toBe('2026-11-09T09:00');
  });

  it('leaves IU empty when there is no source row', () => {
    const seed = applyLogDefaults(
      {
        person_id: 'p1',
        logged_at: '2026-11-16T10:18',
        iu: null,
        is_last_draw: false,
        vial_id: null,
        probably_empty: false,
        source: null
      },
      '2026-11-16T14:30'
    );
    expect(seed.iuText).toBe('');
    expect(seed.sourceLoggedAt).toBeNull();
  });
});