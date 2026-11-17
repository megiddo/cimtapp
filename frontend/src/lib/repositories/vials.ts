import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';
import type { VialSummary } from '../logbook/types';

export async function fetchCurrentVials(baseUrl = ''): Promise<VialSummary[]> {
  const payload = await readAction<VialSummary[]>('/api/v1/vials', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export type CreateVialBody = {
  peptide_id: string;
  person_ids: string[];
  peptide_mg: number;
  bac_water_ml: number;
  lid_color_id?: string | null;
  lid_color_name?: string;
};

export async function createVial(body: CreateVialBody, baseUrl = ''): Promise<DomainResult<VialSummary>> {
  const payload = await readAction<VialSummary>('/api/v1/vials', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save vial.');
}