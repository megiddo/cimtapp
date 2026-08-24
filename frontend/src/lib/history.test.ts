import { describe, expect, it } from 'vitest';
import { formatDayHeading, formatDateTime, formatTime, groupUsesByLocalDay, localDayKey } from './history';

const sample = (id: string, usedAt: string) => ({
  id,
  used_at: usedAt,
  iu: 25,
  peptide_mg: 1.25,
  peptide_type_name: 'Tirzepatide',
  syringe_label: '0.5 mL / 50 IU'
});

describe('history grouping', () => {
  it('keys a local calendar day from a Date', () => {
    const local = new Date(2026, 7, 20, 23, 15);
    expect(localDayKey(local.toISOString(), local)).toBe('2026-08-20');
    expect(formatDayHeading('2026-08-20', 'en-US')).toContain('Aug');
    expect(formatDayHeading('2026-08-20', 'en-US')).toContain('20');
    expect(formatDayHeading('bad', 'en-US')).toBe('bad');
    expect(formatTime('not-a-date')).toBe('');
    expect(formatDateTime('not-a-date')).toBe('');
    expect(formatDateTime(new Date(2026, 7, 20, 9, 5).toISOString(), 'en-US')).toMatch(/Aug/);
    expect(formatDateTime(new Date(2026, 7, 20, 9, 5).toISOString(), 'en-US')).toMatch(/20/);
    expect(formatDateTime(new Date(2026, 7, 20, 9, 5).toISOString(), 'en-US')).toMatch(/2026/);
  });

  it('groups newest-first uses by local day without regrouping later days', () => {
    const morning = new Date(2026, 7, 20, 9, 0);
    const evening = new Date(2026, 7, 20, 21, 0);
    const previous = new Date(2026, 7, 19, 18, 0);
    const uses = [
      sample('e', evening.toISOString()),
      sample('m', morning.toISOString()),
      sample('p', previous.toISOString())
    ];
    const groups = groupUsesByLocalDay(uses, 'en-US');
    expect(groups).toHaveLength(2);
    expect(groups[0].day).toBe('2026-08-20');
    expect(groups[0].uses.map((item) => item.id)).toEqual(['e', 'm']);
    expect(groups[1].day).toBe('2026-08-19');
    expect(groups[1].uses.map((item) => item.id)).toEqual(['p']);
    expect(formatTime(morning.toISOString(), 'en-US').length).toBeGreaterThan(0);
  });
});
