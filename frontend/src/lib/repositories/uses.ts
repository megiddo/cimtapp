import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import type { LoggedUse } from './types';

export async function fetchUses(
  init: { limit?: number; before?: string; profile_id?: string; baseUrl?: string } = {}
): Promise<LoggedUse[]> {
  const params = new URLSearchParams();
  if (init.limit !== undefined) {
    params.set('limit', String(init.limit));
  }
  if (init.before !== undefined) {
    params.set('before', init.before);
  }
  if (init.profile_id !== undefined) {
    params.set('profile_id', init.profile_id);
  }
  const query = params.toString();
  const path = query === '' ? '/api/v1/uses' : `/api/v1/uses?${query}`;
  const payload = await readAction<LoggedUse[]>(path, { baseUrl: init.baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchUse(id: string, baseUrl = ''): Promise<LoggedUse | null> {
  const payload = await readAction<LoggedUse>(`/api/v1/uses/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function logUse(
  body: {
    iu: number;
    syringe_id?: string | null;
    used_at?: string;
    notes?: string | null;
    compound_id?: string;
    profile_id?: string;
  },
  baseUrl = ''
): Promise<DomainResult<LoggedUse>> {
  const payload = await readAction<LoggedUse>('/api/v1/uses', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to log use.');
}

export async function patchUse(
  id: string,
  body: {
    iu?: number;
    syringe_id?: string | null;
    used_at?: string;
    notes?: string | null;
    profile_id?: string;
  },
  baseUrl = ''
): Promise<DomainResult<LoggedUse>> {
  const payload = await readAction<LoggedUse>(`/api/v1/uses/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save use.');
}

export async function deleteUse(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/uses/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  return asDeleteResult(payload, 'Unable to delete use.');
}
