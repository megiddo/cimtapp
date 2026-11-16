import type { LogEntry, VialSummary } from './types';

export function prefillNew(personIds: string[], peptideId = 'tirzepatide'): VialModalPrefill {
  return {
    peptide_id: peptideId,
    person_ids: personIds,
    peptide_mg: '10',
    bac_water_ml: '2',
    lid_color_name: '',
    openPepcalc: false,
    pepcalcMg: '10',
    pepcalcTargetMg: '',
    pepcalcTargetIu: ''
  };
}

export type VialModalPrefill = {
  peptide_id: string;
  person_ids: string[];
  peptide_mg: string;
  bac_water_ml: string;
  lid_color_name: string;
  openPepcalc: boolean;
  pepcalcMg: string;
  pepcalcTargetMg: string;
  pepcalcTargetIu: string;
};

export function prefillRecompound(vial: VialSummary): VialModalPrefill {
  return {
    peptide_id: vial.peptide_id,
    person_ids: vial.people.map((person) => person.person_id),
    peptide_mg: String(vial.peptide_mg),
    bac_water_ml: String(vial.bac_water_ml),
    lid_color_name: vial.lid_color_name,
    openPepcalc: false,
    pepcalcMg: String(vial.peptide_mg),
    pepcalcTargetMg: '',
    pepcalcTargetIu: ''
  };
}

export function prefillRecalculate(vial: VialSummary, latest: LogEntry | null): VialModalPrefill {
  const base = prefillRecompound(vial);
  return {
    ...base,
    openPepcalc: true,
    pepcalcMg: String(vial.peptide_mg),
    pepcalcTargetMg: latest === null ? '' : String(latest.dose_mg),
    pepcalcTargetIu: latest === null ? '' : String(latest.iu)
  };
}