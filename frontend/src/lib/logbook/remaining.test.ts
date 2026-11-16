import { describe, expect, it } from 'vitest';
import { formatMgLeft, perPersonDrawLine } from './remaining';

describe('formatMgLeft', () => {
  it('shows past the label when negative', () => {
    expect(formatMgLeft(-1.5)).toBe('1.50 mg past the label');
    expect(formatMgLeft(7.5)).toBe('7.50 mg left');
  });
});

describe('perPersonDrawLine', () => {
  it('omits a single person and joins multiple', () => {
    expect(perPersonDrawLine([{ name: 'Noah', drawn_mg: 5 }])).toBeNull();
    expect(perPersonDrawLine([
      { name: 'Noah', drawn_mg: 5 },
      { name: 'Sam', drawn_mg: 2.5 }
    ])).toBe('Noah 5.00 mg · Sam 2.50 mg');
  });
});