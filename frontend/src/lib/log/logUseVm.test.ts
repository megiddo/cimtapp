import { describe, expect, it } from 'vitest';
import type { Compound, LoggedUse, Syringe } from '../repositories/types';
import { canSubmitDose, initialLogStep, readLogSeed, seedDoseForProfile, seedIuText } from './logUseVm';

const syringeA: Syringe = {
  id: 's1',
  label: 'A',
  volume_ml: 0.5,
  capacity_iu: 50,
  is_default: false,
  quantity: 2,
  archived_at: null
};
const syringeB: Syringe = { ...syringeA, id: 's2', label: 'B', is_default: true };

const vialA = { id: 'c1', profile_ids: ['p1'] } as Compound;
const vialB = { id: 'c2', profile_ids: ['p2'] } as Compound;

describe('log use wizard', () => {
  it('skips pick when there is one profile or a valid requested id', () => {
    expect(initialLogStep([{ id: 'p1' }], null)).toEqual({ step: 'dose', profileId: 'p1' });
    expect(initialLogStep([{ id: 'p1' }, { id: 'p2' }], 'p2')).toEqual({ step: 'dose', profileId: 'p2' });
    expect(initialLogStep([{ id: 'p1' }, { id: 'p2' }], 'missing')).toEqual({ step: 'pick', profileId: '' });
    expect(initialLogStep([{ id: 'p1' }, { id: 'p2' }], null)).toEqual({ step: 'pick', profileId: '' });
    expect(initialLogStep([], 'p1')).toEqual({ step: 'pick', profileId: '' });
  });

  it('seeds IU from the query, then last use, then 25', () => {
    expect(seedIuText('12.5', 10)).toBe('12.5');
    expect(seedIuText('', 10)).toBe('10');
    expect(seedIuText(null, 8)).toBe('8');
    expect(seedIuText(null, undefined)).toBe('25');
    expect(seedIuText('', undefined)).toBe('25');
  });

  it('filters vials to the profile and prefers requested then last-used stock', () => {
    const last = { compound_id: 'c1', syringe_id: 's1', iu: 15 } as LoggedUse;
    expect(
      seedDoseForProfile({
        profileId: 'p1',
        openVials: [vialA, vialB],
        syringes: [syringeA, syringeB],
        lastUse: last,
        requestedCompoundId: null,
        requestedIu: null
      })
    ).toEqual({
      profileId: 'p1',
      vials: [vialA],
      compoundId: 'c1',
      syringeId: 's1',
      iuText: '15'
    });

    expect(
      seedDoseForProfile({
        profileId: 'p1',
        openVials: [vialA],
        syringes: [syringeA, syringeB],
        lastUse: undefined,
        requestedCompoundId: 'missing',
        requestedIu: '30'
      })
    ).toMatchObject({ compoundId: 'c1', syringeId: 's2', iuText: '30' });
  });

  it('requires a selected vial, parsed IU, and profile to submit', () => {
    expect(canSubmitDose(vialA, 10, 'p1')).toBe(true);
    expect(canSubmitDose(null, 10, 'p1')).toBe(false);
    expect(canSubmitDose(vialA, null, 'p1')).toBe(false);
    expect(canSubmitDose(vialA, 10, '')).toBe(false);
  });

  it('reads optional query seeds', () => {
    expect(readLogSeed(new URLSearchParams('profile_id=p1&compound_id=c1&iu=12'))).toEqual({
      requestedProfileId: 'p1',
      requestedCompoundId: 'c1',
      requestedIu: '12'
    });
    expect(readLogSeed(new URLSearchParams())).toEqual({
      requestedProfileId: null,
      requestedCompoundId: null,
      requestedIu: null
    });
  });
});
