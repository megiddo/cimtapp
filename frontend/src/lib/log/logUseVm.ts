import { defaultOpenVialId, defaultSyringeId, vialsForProfile } from '../domain/defaults';
import type { Compound, LoggedUse, Profile, Syringe } from '../repositories/types';

export type LogStep = 'pick' | 'dose';

export type LogUseSeed = {
  requestedProfileId: string | null;
  requestedCompoundId: string | null;
  requestedIu: string | null;
};

export type SeededDose = {
  profileId: string;
  vials: Compound[];
  compoundId: string;
  syringeId: string;
  iuText: string;
};

export function initialLogStep(profiles: Pick<Profile, 'id'>[], requestedProfileId: string | null): {
  step: LogStep;
  profileId: string;
} {
  if (profiles.length === 1) {
    return { step: 'dose', profileId: profiles[0].id };
  }
  if (requestedProfileId !== null && profiles.some((item) => item.id === requestedProfileId)) {
    return { step: 'dose', profileId: requestedProfileId };
  }
  return { step: 'pick', profileId: '' };
}

export function seedIuText(requestedIu: string | null, lastIu: number | undefined): string {
  if (requestedIu !== null && requestedIu !== '') {
    return requestedIu;
  }
  if (lastIu !== undefined) {
    return String(lastIu);
  }
  return '25';
}

export function seedDoseForProfile(input: {
  profileId: string;
  openVials: Compound[];
  syringes: Syringe[];
  lastUse: LoggedUse | undefined;
  requestedCompoundId: string | null;
  requestedIu: string | null;
}): SeededDose {
  const vials = vialsForProfile(input.openVials, input.profileId);
  return {
    profileId: input.profileId,
    vials,
    compoundId: defaultOpenVialId(vials, input.requestedCompoundId ?? input.lastUse?.compound_id ?? null),
    syringeId: defaultSyringeId(input.syringes, input.lastUse?.syringe_id ?? null),
    iuText: seedIuText(input.requestedIu, input.lastUse?.iu)
  };
}

export function canSubmitDose(selected: Compound | null, iu: number | null, profileId: string): boolean {
  return selected !== null && iu !== null && profileId !== '';
}

export function readLogSeed(params: URLSearchParams): LogUseSeed {
  return {
    requestedProfileId: params.get('profile_id'),
    requestedCompoundId: params.get('compound_id'),
    requestedIu: params.get('iu')
  };
}
