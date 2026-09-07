import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import { inventoryListPath } from '../stock/listQuery';
import type { Syringe } from './types';

export async function fetchSyringes(baseUrl = '', view?: 'all'): Promise<Syringe[]> {
  const payload = await readAction<Syringe[]>(inventoryListPath('syringes', view), { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchSyringe(id: string, baseUrl = ''): Promise<Syringe | null> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function createSyringe(
  body: {
    volume_ml: number;
    capacity_iu: number;
    label?: string;
    is_default?: boolean;
    quantity?: number;
  },
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>('/api/v1/syringes', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add syringe.');
}

export async function patchSyringe(
  id: string,
  body: { label?: string; is_default?: boolean; volume_ml?: number; capacity_iu?: number },
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to update syringe.');
}

export async function deleteSyringe(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/syringes/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  return asDeleteResult(payload, 'Unable to delete syringe.');
}

export async function restockSyringe(id: string, count: number, baseUrl = ''): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}/restock`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ count })
  });
  return asResult(payload, 'Unable to restock syringes.');
}

export async function burnSyringe(id: string, count: number, baseUrl = ''): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}/burn`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ count })
  });
  return asResult(payload, 'Unable to burn syringes.');
}

export async function archiveSyringe(id: string, baseUrl = ''): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}/archive`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  });
  return asResult(payload, 'Unable to archive syringe.');
}
