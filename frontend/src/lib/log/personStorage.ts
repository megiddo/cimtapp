import type { Person } from '../logbook/types';

const KEY = 'peptrack.person';

export function rememberPersonId(id: string): void {
  if (typeof localStorage === 'undefined') {
    return;
  }
  localStorage.setItem(KEY, id);
}

export function readRememberedPersonId(): string | null {
  if (typeof localStorage === 'undefined') {
    return null;
  }
  const value = localStorage.getItem(KEY);
  return value === '' ? null : value;
}

export function pickInitialPersonId(people: Person[], rememberedId: string | null): string {
  if (people.length === 0) {
    return '';
  }
  if (rememberedId !== null && people.some((person) => person.id === rememberedId)) {
    return rememberedId;
  }
  if (people.length === 1) {
    return people[0]?.id ?? '';
  }
  const sorted = [...people].sort((a, b) => a.created_at.localeCompare(b.created_at));
  return sorted[0]?.id ?? '';
}