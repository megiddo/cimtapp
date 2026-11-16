import { formatMg } from '../dose';

export function formatMgLeft(mgLeft: number): string {
  if (mgLeft < -1e-9) {
    return `${formatMg(Math.abs(mgLeft))} mg past the label`;
  }
  return `${formatMg(mgLeft)} mg left`;
}

export function perPersonDrawLine(people: { name: string; drawn_mg: number }[]): string | null {
  if (people.length < 2) {
    return null;
  }
  return people.map((person) => `${person.name} ${formatMg(person.drawn_mg)} mg`).join(' · ');
}