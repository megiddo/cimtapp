import { describe, expect, it } from 'vitest';
import { pickInitialPersonId } from './personStorage';
import type { Person } from '../logbook/types';

const people: Person[] = [
  { id: 'b', name: 'Sam', created_at: '2026-01-02T00:00:00Z', removed_at: null },
  { id: 'a', name: 'Noah', created_at: '2026-01-01T00:00:00Z', removed_at: null }
];

describe('pickInitialPersonId', () => {
  it('uses remembered id when still visible', () => {
    expect(pickInitialPersonId(people, 'b')).toBe('b');
  });

  it('picks earliest person when nothing is remembered', () => {
    expect(pickInitialPersonId(people, null)).toBe('a');
  });

  it('returns empty when there are no people', () => {
    expect(pickInitialPersonId([], null)).toBe('');
  });
});

describe('rememberPersonId', () => {
  it('round-trips localStorage', async () => {
    const { rememberPersonId, readRememberedPersonId } = await import('./personStorage');
    rememberPersonId('p1');
    expect(readRememberedPersonId()).toBe('p1');
  });
});