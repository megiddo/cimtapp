import type { Compound, Syringe } from '../repositories/types';

export function vialsForProfile(vials: Compound[], profileId: string): Compound[] {
  return vials.filter((vial) => (vial.profile_ids ?? []).includes(profileId));
}

export function parseCountInput(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '' || !/^\d+$/.test(trimmed)) {
    return null;
  }
  const value = Number(trimmed);
  if (!Number.isInteger(value) || value <= 0) {
    return null;
  }

  return value;
}

export function defaultSyringeId(syringes: Syringe[], lastUsedId: string | null): string {
  if (lastUsedId !== null && syringes.some((item) => item.id === lastUsedId)) {
    return lastUsedId;
  }
  const flagged = syringes.find((item) => item.is_default);
  return flagged?.id ?? syringes[0]?.id ?? '';
}

export function vialLabel(vial: { name: string; peptide_type_name: string }): string {
  if (vial.name === vial.peptide_type_name) {
    return vial.name;
  }
  return `${vial.name} · ${vial.peptide_type_name}`;
}

export function defaultOpenVialId(vials: { id: string }[], preferredId: string | null): string {
  if (preferredId !== null && vials.some((item) => item.id === preferredId)) {
    return preferredId;
  }
  return vials[0]?.id ?? '';
}
