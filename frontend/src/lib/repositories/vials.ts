import { readAction } from '../api/actions';
import type { CurrentVial } from '../logbook/types';

export async function fetchCurrentVials(baseUrl = ''): Promise<CurrentVial[]> {
  const payload = await readAction<CurrentVial[]>('/api/v1/vials', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}