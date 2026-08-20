import { describe, expect, it } from 'vitest';
import { datetimeLocalToIso, nowDatetimeLocal, toDatetimeLocalValue } from './datetime';

describe('datetime-local helpers', () => {
  it('formats local wall time without seconds', () => {
    const date = new Date(2026, 7, 20, 9, 5, 30);
    expect(toDatetimeLocalValue(date)).toBe('2026-08-20T09:05');
    expect(nowDatetimeLocal(date)).toBe('2026-08-20T09:05');
  });

  it('round-trips a datetime-local value through Date', () => {
    const iso = datetimeLocalToIso('2026-08-20T09:05');
    expect(iso.endsWith('Z')).toBe(true);
    expect(Number.isNaN(Date.parse(iso))).toBe(false);
    expect(datetimeLocalToIso('not-a-date')).toBe('not-a-date');
  });
});
