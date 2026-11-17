import type { Person } from '../logbook/types';

export function splitPeople(visible: Person[], removed: Person[]): { active: Person[]; removed: Person[] } {
  return { active: visible, removed };
}