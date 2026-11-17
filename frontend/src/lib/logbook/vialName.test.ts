import { describe, expect, it } from 'vitest';
import { buildVialDisplayName, previewVialName } from './vialName';

describe('buildVialDisplayName', () => {
  it('matches v2 examples', () => {
    expect(buildVialDisplayName('Tirzepatide', ['Noah'], 10, 2)).toBe('Tirzepatide - Noah - 0.05 mg/IU');
    expect(buildVialDisplayName('Retatrutide', ['Noah', 'Sam'], 10, 1)).toBe('Retatrutide - Noah, Sam - 0.10 mg/IU');
  });

  it('preview shows placeholder until inputs are valid', () => {
    expect(previewVialName('Tirzepatide', [], 10, 2)).toBe('—');
    expect(previewVialName('Tirzepatide', ['Noah'], null, 2)).toBe('—');
    expect(previewVialName('Tirzepatide', ['Noah'], 10, 2)).toBe('Tirzepatide - Noah - 0.05 mg/IU');
  });
});