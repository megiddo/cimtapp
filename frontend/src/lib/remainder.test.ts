import { describe, expect, it } from 'vitest';
import { remainderTone, remainderToneMessage, remainingFraction } from './remainder';

describe('remainder tone', () => {
  it('is default above 20 percent remaining', () => {
    expect(remainingFraction(8.75, 10)).toBe(0.875);
    expect(remainderTone(8.75, 10)).toBe('default');
    expect(remainderToneMessage('default')).toBeNull();
    expect(remainderTone(2.1, 10)).toBe('default');
  });

  it('warns at or below 20 percent', () => {
    expect(remainderTone(2, 10)).toBe('warning');
    expect(remainderTone(1, 10)).toBe('warning');
    expect(remainderToneMessage('warning')).toBe('Low remainder.');
  });

  it('is danger at empty or non-positive peptide', () => {
    expect(remainderTone(0, 10)).toBe('danger');
    expect(remainderTone(-0.01, 10)).toBe('danger');
    expect(remainderTone(1, 0)).toBe('danger');
    expect(remainingFraction(1, 0)).toBe(0);
    expect(remainderToneMessage('danger')).toBe('Vial empty — mix a new compound.');
  });
});
