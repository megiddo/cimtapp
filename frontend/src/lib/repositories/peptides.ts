import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';
import type { Peptide } from '../logbook/types';

export async function fetchPeptides(baseUrl = ''): Promise<Peptide[]> {
  const payload = await readAction<Peptide[]>('/api/v1/peptides', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createPeptide(body: { name: string }, baseUrl = ''): Promise<DomainResult<Peptide>> {
  const payload = await readAction<Peptide>('/api/v1/peptides', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add peptide.');
}