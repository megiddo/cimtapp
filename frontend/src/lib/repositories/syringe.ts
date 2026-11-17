import { readAction } from '../api/actions';
import { asResult, type DomainResult } from '../domain/result';

export type AccountSyringe = {
  volume_ml: number;
  capacity_iu: number;
};

export async function fetchAccountSyringe(baseUrl = ''): Promise<AccountSyringe> {
  const payload = await readAction<AccountSyringe>('/api/v1/syringe', { baseUrl });
  if (payload.data === undefined) {
    throw new Error('Missing account syringe.');
  }
  return payload.data;
}

export async function putAccountSyringe(
  body: AccountSyringe,
  baseUrl = ''
): Promise<DomainResult<AccountSyringe>> {
  const payload = await readAction<AccountSyringe>('/api/v1/syringe', {
    baseUrl,
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to update syringe.');
}