import { readAction } from '../api/actions';
import { asDeleteResult, asResult, type DomainResult } from '../domain/result';
import type { LogDefaults, LogEntry } from '../logbook/types';
import { datetimeLocalToIso } from '../datetime';

export type CreateLogBody = {
  person_id: string;
  vial_id: string;
  iu: number;
  logged_at: string;
  is_last_draw: boolean;
  notes?: string | null;
};

export function buildCreateLogBody(fields: {
  person_id: string;
  vial_id: string;
  iu: number;
  logged_at: string;
  is_last_draw: boolean;
  notes?: string;
}): CreateLogBody {
  return {
    person_id: fields.person_id,
    vial_id: fields.vial_id,
    iu: fields.iu,
    logged_at: datetimeLocalToIso(fields.logged_at),
    is_last_draw: fields.is_last_draw,
    notes: fields.notes === '' || fields.notes === undefined ? null : fields.notes
  };
}

export async function fetchLogDefaults(personId: string, baseUrl = ''): Promise<LogDefaults> {
  const payload = await readAction<LogDefaults>(
    `/api/v1/log/defaults?person_id=${encodeURIComponent(personId)}`,
    { baseUrl }
  );
  if (payload.data === undefined) {
    throw new Error('Missing log defaults.');
  }
  return payload.data;
}

export async function fetchLogEntries(
  personId: string,
  options: { limit?: number; baseUrl?: string } = {}
): Promise<LogEntry[]> {
  const params = new URLSearchParams({ person_id: personId });
  if (options.limit !== undefined) {
    params.set('limit', String(options.limit));
  }
  const payload = await readAction<LogEntry[]>(`/api/v1/log?${params.toString()}`, {
    baseUrl: options.baseUrl ?? ''
  });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchLogEntry(id: string, baseUrl = ''): Promise<LogEntry> {
  const payload = await readAction<LogEntry>(`/api/v1/log/${id}`, { baseUrl });
  if (payload.data === undefined) {
    throw new Error('Missing log entry.');
  }
  return payload.data;
}

export async function createLogEntry(body: CreateLogBody, baseUrl = ''): Promise<DomainResult<LogEntry>> {
  const payload = await readAction<LogEntry>('/api/v1/log', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save log entry.');
}

export async function patchLogEntry(
  id: string,
  body: CreateLogBody,
  baseUrl = ''
): Promise<DomainResult<LogEntry>> {
  const payload = await readAction<LogEntry>(`/api/v1/log/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to update log entry.');
}

export async function deleteLogEntry(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/log/${id}`, { baseUrl, method: 'DELETE' });
  return asDeleteResult(payload, 'Unable to delete log entry.');
}