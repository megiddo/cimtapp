import { concentration, formatMg } from '../dose';

export function mgPerIuFromConcentration(
  concentrationMgPerMl: number,
  syringeVolumeMl = 0.5,
  syringeCapacityIu = 50
): number {
  const iuPerMl = syringeCapacityIu / syringeVolumeMl;
  return concentrationMgPerMl / iuPerMl;
}

export function formatMgPerIuRate(
  concentrationMgPerMl: number,
  syringeVolumeMl = 0.5,
  syringeCapacityIu = 50
): string {
  return `${formatMg(mgPerIuFromConcentration(concentrationMgPerMl, syringeVolumeMl, syringeCapacityIu))} mg/IU`;
}

export function buildVialDisplayName(
  peptideName: string,
  personNames: string[],
  peptideMg: number,
  bacMl: number,
  syringeVolumeMl = 0.5,
  syringeCapacityIu = 50
): string {
  const people = personNames.join(', ');
  const conc = concentration(peptideMg, bacMl);
  const rate = formatMgPerIuRate(conc, syringeVolumeMl, syringeCapacityIu);
  return `${peptideName} - ${people} - ${rate}`;
}

export function previewVialName(
  peptideName: string,
  personNames: string[],
  peptideMg: number | null,
  bacMl: number | null,
  syringeVolumeMl = 0.5,
  syringeCapacityIu = 50
): string {
  if (personNames.length === 0 || peptideMg === null || bacMl === null || !(peptideMg > 0) || !(bacMl > 0)) {
    return '—';
  }
  return buildVialDisplayName(peptideName, personNames, peptideMg, bacMl, syringeVolumeMl, syringeCapacityIu);
}