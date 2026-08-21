import { describe, expect, it } from 'vitest';
import {
  concentration,
  concentrationFromUse,
  exceedsRemainder,
  formatConcentration,
  formatDoseLine,
  formatIu,
  formatMg,
  formatMl,
  hasAtMostOneDecimal,
  isPositiveIu,
  mlPerIu,
  parseIuInput,
  peptideMgAtConcentration,
  peptideMgFromDose,
  previewDose,
  remainingFor,
  roundMg,
  roundVolume,
  stepIu,
  syringeLabel,
  trimNumber,
  unusualConcentration,
  volumeMl,
  waterNeededPerBottle
} from './dose';

describe('dose formulas', () => {
  it('matches the tirzepatide worked example', () => {
    expect(concentration(10, 2)).toBe(5);
    expect(mlPerIu(0.5, 50)).toBe(0.01);
    expect(volumeMl(25, 0.5, 50)).toBe(0.25);
    expect(peptideMgFromDose(25, 10, 2, 0.5, 50)).toBe(1.25);
    const remainder = remainingFor(10, 1.25, 2, 0.5, 50);
    expect(remainder.remainingMg).toBe(8.75);
    expect(remainder.remainingMl).toBe(1.75);
    expect(remainder.remainingIu).toBe(175);
    expect(remainder.concentration).toBe(5);
  });

  it('does not assume U-100 for a 1 mL / 40 IU syringe', () => {
    expect(mlPerIu(1, 40)).toBe(0.025);
    expect(volumeMl(25, 1, 40)).toBe(0.625);
    expect(peptideMgFromDose(25, 10, 2, 1, 40)).toBe(3.125);
    const remainder = remainingFor(10, 3.125, 2, 1, 40);
    expect(remainder.remainingMg).toBe(6.875);
    expect(remainder.remainingMl).toBe(1.375);
    expect(remainder.remainingIu).toBe(55);
  });

  it('rounds milligrams to four places and volume to six', () => {
    expect(roundMg(1.23456)).toBe(1.2346);
    expect(roundMg(1.23454)).toBe(1.2345);
    expect(roundVolume(0.0000014)).toBe(0.000001);
  });

  it('allows exactly remaining milligrams and rejects overdraw', () => {
    expect(exceedsRemainder(8.75, 8.75)).toBe(false);
    expect(exceedsRemainder(8.7501, 8.75)).toBe(true);
    expect(exceedsRemainder(0, 0)).toBe(false);
  });

  it('validates IU to one decimal and steps by one', () => {
    expect(isPositiveIu(0)).toBe(false);
    expect(isPositiveIu(-1)).toBe(false);
    expect(isPositiveIu(25.12)).toBe(false);
    expect(isPositiveIu(25.5)).toBe(true);
    expect(hasAtMostOneDecimal(25)).toBe(true);
    expect(stepIu(25, 1)).toBe(26);
    expect(stepIu(0.1, -1)).toBe(0.1);
    expect(stepIu(1, -1)).toBe(0.1);
    expect(parseIuInput('')).toBeNull();
    expect(parseIuInput(' 25.5 ')).toBe(25.5);
    expect(parseIuInput('nope')).toBeNull();
  });

  it('returns a live preview or null for invalid inputs', () => {
    const preview = previewDose(25, 10, 2, 0.5, 50);
    expect(preview).toEqual({
      concentration: 5,
      volumeMl: 0.25,
      peptideMg: 1.25,
      remainingMg: 8.75,
      remainingMl: 1.75,
      remainingIu: 175
    });
    expect(previewDose(0, 10, 2, 0.5, 50)).toBeNull();
    expect(previewDose(25, 0, 2, 0.5, 50)).toBeNull();
    expect(previewDose(25, 10, 0, 0.5, 50)).toBeNull();
    expect(previewDose(25, 10, 2, 0, 50)).toBeNull();
    expect(previewDose(25, 10, 2, 0.5, 0)).toBeNull();
  });

  it('rebuilds milligrams from a logged use concentration', () => {
    const conc = concentrationFromUse(1.25, 0.25);
    expect(conc).toBe(5);
    expect(peptideMgAtConcentration(10, conc ?? 0, 0.5, 50)).toBe(0.5);
    expect(concentrationFromUse(1.25, 0)).toBeNull();
    expect(concentrationFromUse(-1, 0.25)).toBeNull();
  });

  it('formats clinical copy and flags unusual concentrations', () => {
    expect(formatIu(25)).toBe('25');
    expect(formatIu(18.5)).toBe('18.5');
    expect(formatMg(1.25)).toBe('1.25');
    expect(formatMg(3.125)).toBe('3.125');
    expect(formatMl(0.25)).toBe('0.25');
    expect(formatMl(1.375)).toBe('1.375');
    expect(formatConcentration(5)).toBe('5.00 mg/mL');
    expect(formatDoseLine(25, 1.25)).toBe('25 IU · 1.25 mg');
    expect(unusualConcentration(0.4)).toBe(true);
    expect(unusualConcentration(20.1)).toBe(true);
    expect(unusualConcentration(0.5)).toBe(false);
    expect(unusualConcentration(20)).toBe(false);
    expect(unusualConcentration(5)).toBe(false);
  });

  it('solves bacteriostatic water from pepcalc-style mix targets', () => {
    expect(waterNeededPerBottle(10, 2.5, 25, 0.5, 50)).toBe(1);
    expect(waterNeededPerBottle(10, 1.25, 25, 0.5, 50)).toBe(2);
    expect(waterNeededPerBottle(5, 0.25, 10, 1, 100)).toBe(2);
    expect(waterNeededPerBottle(0, 2.5, 25, 0.5, 50)).toBeNull();
    expect(waterNeededPerBottle(10, 0, 25, 0.5, 50)).toBeNull();
    expect(waterNeededPerBottle(10, 2.5, 0, 0.5, 50)).toBeNull();
    expect(waterNeededPerBottle(10, 2.5, 25, 0, 50)).toBeNull();
    expect(waterNeededPerBottle(10, 2.5, 25, 0.5, 0)).toBeNull();
  });

  it('builds auto syringe labels without trailing zeros', () => {
    expect(syringeLabel(0.5, 50)).toBe('0.5 mL / 50 IU');
    expect(syringeLabel(1, 40)).toBe('1 mL / 40 IU');
    expect(trimNumber(1.25)).toBe('1.25');
    expect(trimNumber(0.5)).toBe('0.5');
  });
});
