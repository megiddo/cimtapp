import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import type { Profile } from './types';

export const PROFILE_MAX = 5;

export async function fetchProfiles(baseUrl = ''): Promise<Profile[]> {
  const payload = await readAction<Profile[]>('/api/v1/profiles', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createProfile(body: { name: string }, baseUrl = ''): Promise<DomainResult<Profile>> {
  const payload = await readAction<Profile>('/api/v1/profiles', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add profile.');
}

export async function patchProfile(id: string, body: { name: string }, baseUrl = ''): Promise<DomainResult<Profile>> {
  const payload = await readAction<Profile>(`/api/v1/profiles/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to rename profile.');
}

export async function deleteProfile(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/profiles/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  return asDeleteResult(payload, 'Unable to delete profile.');
}
