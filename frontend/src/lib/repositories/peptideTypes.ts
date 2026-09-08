import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';
import type { PeptideType } from './types';

export async function fetchPeptideTypes(baseUrl = ''): Promise<PeptideType[]> {
  const payload = await readAction<PeptideType[]>('/api/v1/peptide-types', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createPeptideType(body: { name: string }, baseUrl = ''): Promise<DomainResult<PeptideType>> {
  const payload = await readAction<PeptideType>('/api/v1/peptide-types', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add peptide.');
}
