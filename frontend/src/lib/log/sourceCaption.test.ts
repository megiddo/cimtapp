import { describe, expect, it } from 'vitest';
import { formatSourceCaption } from './sourceCaption';

describe('formatSourceCaption', () => {
  it('formats a source dose date', () => {
    expect(formatSourceCaption('2026-11-09T09:00')).toBe('Same as Nov 9');
  });

  it('returns null when there is no source', () => {
    expect(formatSourceCaption(null)).toBeNull();
    expect(formatSourceCaption('')).toBeNull();
  });
});