import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';
import type { Person } from '../logbook/types';

export async function fetchPeople(baseUrl = ''): Promise<Person[]> {
  const payload = await readAction<Person[]>('/api/v1/people', { baseUrl });
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