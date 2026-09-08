import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import { inventoryListPath } from '../stock/listQuery';
import type { BacBottle } from './types';

export async function fetchBacBottles(baseUrl = '', view?: 'all'): Promise<BacBottle[]> {
  const payload = await readAction<BacBottle[]>(inventoryListPath('bac-bottles', view), { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchBacBottle(id: string, baseUrl = ''): Promise<BacBottle | null> {
  const payload = await readAction<BacBottle>(`/api/v1/bac-bottles/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function fetchCurrentBacBottle(baseUrl = ''): Promise<BacBottle | null> {
  const payload = await readAction<BacBottle>('/api/v1/bac-bottles/current', { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function addBacBottle(
  body: { volume_ml: number; opened_at?: string; notes?: string | null },
  baseUrl = ''
): Promise<DomainResult<BacBottle>> {
  const payload = await readAction<BacBottle>('/api/v1/bac-bottles', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add bacteriostatic water.');
}

export async function patchBacBottle(
  id: string,
  body: { opened_at?: string; notes?: string | null },
  baseUrl = ''
): Promise<DomainResult<BacBottle>> {
  const payload = await readAction<BacBottle>(`/api/v1/bac-bottles/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save bottle.');
}

export async function burnBacBottle(id: string, ml: number, baseUrl = ''): Promise<DomainResult<BacBottle>> {
  const payload = await readAction<BacBottle>(`/api/v1/bac-bottles/${id}/burn`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ml })
  });
  return asResult(payload, 'Unable to burn bacteriostatic water.');
}

export async function archiveBacBottle(id: string, baseUrl = ''): Promise<DomainResult<BacBottle>> {
  const payload = await readAction<BacBottle>(`/api/v1/bac-bottles/${id}/archive`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  });
  return asResult(payload, 'Unable to archive bottle.');
}

export async function deleteBacBottle(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/bac-bottles/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  return asDeleteResult(payload, 'Unable to delete bottle.');
}
