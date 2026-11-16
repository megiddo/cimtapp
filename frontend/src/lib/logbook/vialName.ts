import { concentration, formatConcentration } from '../dose';

export function buildVialDisplayName(peptideName: string, personNames: string[], peptideMg: number, bacMl: number): string {
  const people = personNames.join(', ');
  const conc = formatConcentration(concentration(peptideMg, bacMl));
  return `${peptideName} - ${people} - ${conc}`;
}

export function previewVialName(
  peptideName: string,
  personNames: string[],
  peptideMg: number | null,
  bacMl: number | null
): string {
  if (personNames.length === 0 || peptideMg === null || bacMl === null || !(peptideMg > 0) || !(bacMl > 0)) {
    return '—';
  }
  return buildVialDisplayName(peptideName, personNames, peptideMg, bacMl);
}