import { describe, expect, it } from 'vitest';
import { nameFieldError } from './fieldError';
import { splitPeople } from './peopleGroups';
import { isValidSyringe } from './syringeForm';

describe('settings helpers', () => {
  it('maps API name errors', () => {
    expect(nameFieldError({ name: ['Taken.'] })).toBe('Taken.');
    expect(nameFieldError({})).toBe('');
  });

  it('splits visible and removed people lists', () => {
    const active = [{ id: '1', name: 'Noah', created_at: '', removed_at: null }];
    const removed = [{ id: '2', name: 'Sam', created_at: '', removed_at: '2026-01-01' }];
    expect(splitPeople(active, removed)).toEqual({ active, removed });
  });

  it('refuses zero syringe values before submit', () => {
    expect(isValidSyringe(0.5, 50)).toBe(true);
    expect(isValidSyringe(0, 50)).toBe(false);
    expect(isValidSyringe(0.5, 0)).toBe(false);
  });
});