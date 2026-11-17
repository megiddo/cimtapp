import { describe, expect, it } from 'vitest';
import { prefillNew, prefillRecompound, prefillRecalculate } from './vialModalPrefill';
import type { LogEntry, VialSummary } from './types';

const vial: VialSummary = {
  id: 'v1',
  peptide_id: 'tirzepatide',
  peptide_name: 'Tirzepatide',
  display_name: 'Tirzepatide - Noah - 0.05 mg/IU',
  lid_color_name: 'Blue',
  peptide_mg: 10,
  bac_water_ml: 2,
  concentration_mg_per_ml: 5,
  mg_left: 8,
  probably_empty: false,
  people: [{ person_id: 'p1', name: 'Noah', drawn_mg: 2 }]
};

const latest: LogEntry = {
  id: 'e1',
  person_id: 'p1',
  person_name: 'Noah',
  vial_id: 'v1',
  vial_name: vial.display_name,
  lid_color_name: 'Blue',
  peptide_name: 'Tirzepatide',
  bac_water_ml: 2,
  peptide_mg_in_vial: 10,
  concentration_mg_per_ml: 5,
  iu: 25,
  dose_volume_ml: 0.25,
  dose_mg: 1.25,
  syringe_volume_ml: 0.5,
  syringe_capacity_iu: 50,
  logged_at: '2026-11-09T09:00',
  is_last_draw: false,
  notes: null
};

describe('vialModalPrefill', () => {
  it('seeds a new modal from selected people', () => {
    const seed = prefillNew(['p1'], 'retatrutide');
    expect(seed.person_ids).toEqual(['p1']);
    expect(seed.peptide_id).toBe('retatrutide');
    expect(seed.peptide_mg).toBe('10');
  });

  it('recompound copies the current vial mix', () => {
    const seed = prefillRecompound(vial);
    expect(seed.peptide_mg).toBe('10');
    expect(seed.bac_water_ml).toBe('2');
    expect(seed.lid_color_name).toBe('Blue');
    expect(seed.openPepcalc).toBe(false);
  });

  it('recalculate opens pepcalc with dose hints', () => {
    const seed = prefillRecalculate(vial, latest);
    expect(seed.openPepcalc).toBe(true);
    expect(seed.pepcalcTargetMg).toBe('1.25');
    expect(seed.pepcalcTargetIu).toBe('25');
  });
});