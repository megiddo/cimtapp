import { describe, expect, it } from 'vitest';
import { pepcalcBacWater } from './pepcalcApply';

describe('pepcalcBacWater', () => {
  it('computes BAC for the checklist example', () => {
    const bac = pepcalcBacWater(10, 1.25, 25, 0.5, 50);
    expect(bac).not.toBeNull();
    expect(bac).toBeCloseTo(2, 2);
  });
});