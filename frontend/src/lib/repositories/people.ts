import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';
import type { Person } from '../logbook/types';

export async function fetchPeople(baseUrl = ''): Promise<Person[]> {
  const payload = await readAction<Person[]>('/api/v1/people', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchRemovedPeople(baseUrl = ''): Promise<Person[]> {
  const payload = await readAction<Person[]>('/api/v1/people/removed', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createPerson(body: { name: string }, baseUrl = ''): Promise<DomainResult<Person>> {
  const payload = await readAction<Person>('/api/v1/people', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add person.');
}

export async function patchPerson(id: string, body: { name: string }, baseUrl = ''): Promise<DomainResult<Person>> {
  const payload = await readAction<Person>(`/api/v1/people/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to rename person.');
}

export async function removePerson(id: string, baseUrl = ''): Promise<DomainResult<Person>> {
  const payload = await readAction<Person>(`/api/v1/people/${id}/remove`, { baseUrl, method: 'POST' });
  return asResult(payload, 'Unable to remove person.');
}

export async function restorePerson(id: string, baseUrl = ''): Promise<DomainResult<Person>> {
  const payload = await readAction<Person>(`/api/v1/people/${id}/restore`, { baseUrl, method: 'POST' });
  return asResult(payload, 'Unable to restore person.');
}