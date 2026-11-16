import { describe, expect, it } from 'vitest';
import { buildVialDisplayName, previewVialName } from './vialName';

describe('buildVialDisplayName', () => {
  it('matches v2 examples', () => {
    expect(buildVialDisplayName('Tirzepatide', ['Noah'], 10, 2)).toBe('Tirzepatide - Noah - 5.00 mg/mL');
    expect(buildVialDisplayName('Retatrutide', ['Noah', 'Sam'], 10, 1)).toBe('Retatrutide - Noah, Sam - 10.00 mg/mL');
  });

  it('preview shows placeholder until inputs are valid', () => {
    expect(previewVialName('Tirzepatide', [], 10, 2)).toBe('—');
    expect(previewVialName('Tirzepatide', ['Noah'], null, 2)).toBe('—');
    expect(previewVialName('Tirzepatide', ['Noah'], 10, 2)).toBe('Tirzepatide - Noah - 5.00 mg/mL');
  });
});