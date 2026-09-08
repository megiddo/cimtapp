import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import { inventoryListPath } from '../stock/listQuery';
import type { Compound } from './types';

export async function fetchCompounds(baseUrl = '', view?: 'all'): Promise<Compound[]> {
  const payload = await readAction<Compound[]>(inventoryListPath('compounds', view), { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchOpenCompounds(baseUrl = ''): Promise<Compound[]> {
  const payload = await readAction<Compound[]>('/api/v1/compounds/open', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchCompound(id: string, baseUrl = ''): Promise<Compound | null> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function fetchCurrentCompound(baseUrl = ''): Promise<Compound | null> {
  const payload = await readAction<Compound>('/api/v1/compounds/current', { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function mixCompound(
  body: {
    peptide_type_id: string;
    peptide_mg: number;
    bac_water_ml: number;
    compounded_at: string;
    name?: string;
    is_open?: boolean;
    notes?: string | null;
    profile_ids?: string[];
  },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>('/api/v1/compounds', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to mix vial.');
}

export async function patchCompound(
  id: string,
  body: {
    peptide_type_id?: string;
    peptide_mg?: number;
    bac_water_ml?: number;
    compounded_at?: string;
    name?: string;
    is_open?: boolean;
    notes?: string | null;
    profile_ids?: string[];
  },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save vial.');
}

export async function deleteCompound(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/compounds/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  return asDeleteResult(payload, 'Unable to delete vial.');
}

export async function adjustCompound(
  id: string,
  body: { remaining_ml: number; notes?: string | null },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}/adjust`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to adjust remaining volume.');
}

export async function archiveCompound(id: string, baseUrl = ''): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}/archive`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  });
  return asResult(payload, 'Unable to archive vial.');
}
