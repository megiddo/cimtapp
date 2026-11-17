import type { LogDefaults } from '../logbook/types';

export type LogFormSeed = {
  vialId: string;
  iuText: string;
  isLastDraw: boolean;
  loggedAt: string;
  sourceLoggedAt: string | null;
};

export function applyLogDefaults(defaults: LogDefaults, clientLoggedAt: string): LogFormSeed {
  return {
    vialId: defaults.vial_id ?? '',
    iuText: defaults.iu === null ? '' : String(defaults.iu),
    isLastDraw: defaults.is_last_draw,
    loggedAt: clientLoggedAt,
    sourceLoggedAt: defaults.source?.logged_at ?? null
  };
}