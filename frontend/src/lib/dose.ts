export const MG_DECIMALS = 4;
export const VOLUME_DECIMALS = 6;
export const IU_DECIMALS = 1;
export const CONCENTRATION_WARN_LOW = 0.5;
export const CONCENTRATION_WARN_HIGH = 20;

export type DosePreview = {
  concentration: number;
  volumeMl: number;
  peptideMg: number;
  remainingMg: number;
  remainingMl: number;
  remainingIu: number;
};

export function roundMg(mg: number): number {
  return roundTo(mg, MG_DECIMALS);
}

export function roundVolume(volumeMl: number): number {
  return roundTo(volumeMl, VOLUME_DECIMALS);
}

export function roundIu(iu: number): number {
  return roundTo(iu, IU_DECIMALS);
}

export function roundTo(value: number, decimals: number): number {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

export function hasAtMostOneDecimal(value: number): boolean {
  return Math.abs(value * 10 - Math.round(value * 10)) < 1e-6;
}

export function isPositiveIu(iu: number): boolean {
  return Number.isFinite(iu) && iu > 0 && hasAtMostOneDecimal(iu);
}

export function concentration(peptideMg: number, bacWaterMl: number): number {
  return peptideMg / bacWaterMl;
}

export function mlPerIu(syringeVolumeMl: number, syringeCapacityIu: number): number {
  return syringeVolumeMl / syringeCapacityIu;
}

export function volumeMl(iu: number, syringeVolumeMl: number, syringeCapacityIu: number): number {
  return roundVolume(iu * mlPerIu(syringeVolumeMl, syringeCapacityIu));
}

export function peptideMgFromDose(
  iu: number,
  compoundPeptideMg: number,
  bacWaterMl: number,
  syringeVolumeMl: number,
  syringeCapacityIu: number
): number {
  return roundMg(
    volumeMl(iu, syringeVolumeMl, syringeCapacityIu) * concentration(compoundPeptideMg, bacWaterMl)
  );
}

export function remainingFor(
  compoundPeptideMg: number,
  usedPeptideMgSum: number,
  bacWaterMl: number,
  syringeVolumeMl: number,
  syringeCapacityIu: number
): Omit<DosePreview, 'volumeMl' | 'peptideMg'> {
  const conc = concentration(compoundPeptideMg, bacWaterMl);
  const remainingMg = roundMg(compoundPeptideMg - usedPeptideMgSum);
  const remainingMl = roundVolume(remainingMg / conc);
  const remainingIu = roundIu(remainingMl / mlPerIu(syringeVolumeMl, syringeCapacityIu));
  return {
    concentration: conc,
    remainingMg,
    remainingMl,
    remainingIu
  };
}

export function previewDose(
  iu: number,
  compoundPeptideMg: number,
  bacWaterMl: number,
  syringeVolumeMl: number,
  syringeCapacityIu: number
): DosePreview | null {
  if (!isPositiveIu(iu) || compoundPeptideMg <= 0 || bacWaterMl <= 0) {
    return null;
  }
  if (syringeVolumeMl <= 0 || syringeCapacityIu <= 0) {
    return null;
  }

  const conc = concentration(compoundPeptideMg, bacWaterMl);
  const vol = volumeMl(iu, syringeVolumeMl, syringeCapacityIu);
  const peptideMg = roundMg(vol * conc);
  return {
    concentration: conc,
    volumeMl: vol,
    peptideMg,
    remainingMg: roundMg(compoundPeptideMg - peptideMg),
    remainingMl: roundVolume((compoundPeptideMg - peptideMg) / conc),
    remainingIu: roundIu((compoundPeptideMg - peptideMg) / conc / mlPerIu(syringeVolumeMl, syringeCapacityIu))
  };
}

export function peptideMgAtConcentration(
  iu: number,
  concentrationMgPerMl: number,
  syringeVolumeMl: number,
  syringeCapacityIu: number
): number {
  return roundMg(volumeMl(iu, syringeVolumeMl, syringeCapacityIu) * concentrationMgPerMl);
}

export function concentrationFromUse(peptideMg: number, volumeMlValue: number): number | null {
  if (!(volumeMlValue > 0) || !(peptideMg >= 0)) {
    return null;
  }
  return peptideMg / volumeMlValue;
}

export function exceedsRemainder(doseMg: number, remainingMg: number): boolean {
  return roundMg(doseMg) > roundMg(remainingMg);
}

export function stepIu(current: number, delta: number): number {
  const next = roundIu(current + delta);
  return next <= 0 ? 0.1 : next;
}

export function formatIu(iu: number): string {
  const rounded = roundIu(iu);
  if (Math.abs(rounded - Math.round(rounded)) < 1e-9) {
    return String(Math.round(rounded));
  }
  return rounded.toFixed(IU_DECIMALS);
}

export function formatMg(mg: number): string {
  const three = roundTo(mg, 3);
  const two = roundTo(mg, 2);
  if (Math.abs(three - two) < 1e-9) {
    return two.toFixed(2);
  }
  return three.toFixed(3);
}

export function formatMl(ml: number): string {
  const three = roundTo(ml, 3);
  const two = roundTo(ml, 2);
  if (Math.abs(three - two) < 1e-9) {
    return two.toFixed(2);
  }
  return three.toFixed(3);
}

export function formatConcentration(mgPerMl: number): string {
  return `${formatMg(mgPerMl)} mg/mL`;
}

export function formatDoseLine(iu: number, mg: number): string {
  return `${formatIu(iu)} IU · ${formatMg(mg)} mg`;
}

export function unusualConcentration(mgPerMl: number): boolean {
  return mgPerMl < CONCENTRATION_WARN_LOW || mgPerMl > CONCENTRATION_WARN_HIGH;
}

export function parseIuInput(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '') {
    return null;
  }
  const value = Number(trimmed);
  if (!Number.isFinite(value)) {
    return null;
  }
  return value;
}
