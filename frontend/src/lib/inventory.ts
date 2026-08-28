import { readAction } from './auth';
import { fieldErrorsFrom, genericErrorMessage, isValidationError, type FieldMap } from './payload';

export type PeptideType = {
  id: string;
  slug: string;
  name: string;
  sort_order: number;
};

export type Syringe = {
  id: string;
  label: string;
  volume_ml: number;
  capacity_iu: number;
  is_default: boolean;
  quantity: number;
};

export type BacBottle = {
  id: string;
  volume_ml: number;
  remaining_ml: number;
  opened_at: string;
  notes: string | null;
  created_at: string;
  archived_at: string | null;
  is_current: boolean;
};

export type Compound = {
  id: string;
  name: string;
  is_open: boolean;
  archived_at: string | null;
  peptide_type_id: string;
  peptide_type_slug: string;
  peptide_type_name: string;
  peptide_mg: number;
  bac_water_ml: number;
  compounded_at: string;
  notes: string | null;
  created_at: string;
  bac_bottle_id: string | null;
  has_uses: boolean;
  adjustment_mg: number;
  remaining_mg: number;
  remaining_ml: number;
  remaining_iu: number;
  concentration: number;
};

export type LoggedUse = {
  id: string;
  compound_id: string;
  compound_name: string;
  peptide_type_name: string;
  iu: number;
  syringe_id: string | null;
  syringe_label: string | null;
  syringe_volume_ml: number;
  syringe_capacity_iu: number;
  volume_ml: number;
  peptide_mg: number;
  used_at: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
};

export type DomainResult<T> =
  | { ok: true; data: T; status: number }
  | { ok: false; status: number; fields: FieldMap; message: string; remainingIu: number | null };

function fail<T>(status: number, fields: FieldMap, message: string, remainingIu: number | null): DomainResult<T> {
  return { ok: false, status, fields, message, remainingIu };
}

export function remainingIuFrom(payload: { error?: { remaining_iu?: unknown } }): number | null {
  const value = payload.error?.remaining_iu;
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

async function asResult<T>(
  payload: Awaited<ReturnType<typeof readAction<T>>>,
  fallback: string
): Promise<DomainResult<T>> {
  if (payload.statusCode >= 200 && payload.statusCode < 300 && payload.data !== undefined) {
    return { ok: true, data: payload.data as T, status: payload.statusCode };
  }
  return fail(
    payload.statusCode,
    fieldErrorsFrom(payload),
    genericErrorMessage(payload, isValidationError(payload) ? 'Check the highlighted fields.' : fallback),
    remainingIuFrom(payload)
  );
}

export async function fetchPeptideTypes(baseUrl = ''): Promise<PeptideType[]> {
  const payload = await readAction<PeptideType[]>('/api/v1/peptide-types', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createPeptideType(
  body: { name: string },
  baseUrl = ''
): Promise<DomainResult<PeptideType>> {
  const payload = await readAction<PeptideType>('/api/v1/peptide-types', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add peptide.');
}

export async function fetchSyringes(baseUrl = ''): Promise<Syringe[]> {
  const payload = await readAction<Syringe[]>('/api/v1/syringes', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchSyringe(id: string, baseUrl = ''): Promise<Syringe | null> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function fetchCompounds(baseUrl = ''): Promise<Compound[]> {
  const payload = await readAction<Compound[]>('/api/v1/compounds', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchOpenCompounds(baseUrl = ''): Promise<Compound[]> {
  const payload = await readAction<Compound[]>('/api/v1/compounds/open', { baseUrl });
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function fetchCompound(id: string, baseUrl = ''): Promise<Compound | null> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}`, { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function fetchCurrentCompound(baseUrl = ''): Promise<Compound | null> {
  const payload = await readAction<Compound>('/api/v1/compounds/current', { baseUrl });
  if (payload.statusCode === 404) {
    return null;
  }
  return payload.data ?? null;
}

export async function fetchBacBottles(baseUrl = ''): Promise<BacBottle[]> {
  const payload = await readAction<BacBottle[]>('/api/v1/bac-bottles', { baseUrl });
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

export async function burnBacBottle(
  id: string,
  ml: number,
  baseUrl = ''
): Promise<DomainResult<BacBottle>> {
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
  if (payload.statusCode >= 200 && payload.statusCode < 300) {
    return { ok: true, data: null, status: payload.statusCode };
  }
  return fail(
    payload.statusCode,
    fieldErrorsFrom(payload),
    genericErrorMessage(payload, 'Unable to delete bottle.'),
    remainingIuFrom(payload)
  );
}

export async function fetchUses(
  init: { limit?: number; before?: string; baseUrl?: string } = {}
): Promise<LoggedUse[]> {
  const params = new URLSearchParams();
  if (init.limit !== undefined) {
    params.set('limit', String(init.limit));
  }
  if (init.before !== undefined) {
    params.set('before', init.before);
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

export async function mixCompound(
  body: {
    peptide_type_id: string;
    peptide_mg: number;
    bac_water_ml: number;
    compounded_at: string;
    name?: string;
    is_open?: boolean;
    notes?: string | null;
  },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>('/api/v1/compounds', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to mix vial.');
}

export async function patchCompound(
  id: string,
  body: {
    peptide_type_id?: string;
    peptide_mg?: number;
    bac_water_ml?: number;
    compounded_at?: string;
    name?: string;
    is_open?: boolean;
    notes?: string | null;
  },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to save vial.');
}

export async function deleteCompound(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/compounds/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  if (payload.statusCode >= 200 && payload.statusCode < 300) {
    return { ok: true, data: null, status: payload.statusCode };
  }
  return fail(
    payload.statusCode,
    fieldErrorsFrom(payload),
    genericErrorMessage(payload, 'Unable to delete vial.'),
    remainingIuFrom(payload)
  );
}

export async function adjustCompound(
  id: string,
  body: { remaining_ml: number; notes?: string | null },
  baseUrl = ''
): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}/adjust`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to adjust remaining volume.');
}

export async function archiveCompound(id: string, baseUrl = ''): Promise<DomainResult<Compound>> {
  const payload = await readAction<Compound>(`/api/v1/compounds/${id}/archive`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({})
  });
  return asResult(payload, 'Unable to archive vial.');
}

export async function logUse(
  body: {
    iu: number;
    syringe_id?: string | null;
    used_at?: string;
    notes?: string | null;
    compound_id?: string;
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
  if (payload.statusCode >= 200 && payload.statusCode < 300) {
    return { ok: true, data: null, status: payload.statusCode };
  }
  return fail(
    payload.statusCode,
    fieldErrorsFrom(payload),
    genericErrorMessage(payload, 'Unable to delete use.'),
    remainingIuFrom(payload)
  );
}

export async function createSyringe(
  body: {
    volume_ml: number;
    capacity_iu: number;
    label?: string;
    is_default?: boolean;
    quantity?: number;
  },
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>('/api/v1/syringes', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to add syringe.');
}

export async function patchSyringe(
  id: string,
  body: { label?: string; is_default?: boolean; volume_ml?: number; capacity_iu?: number },
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}`, {
    baseUrl,
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body)
  });
  return asResult(payload, 'Unable to update syringe.');
}

export async function deleteSyringe(id: string, baseUrl = ''): Promise<DomainResult<null>> {
  const payload = await readAction<null>(`/api/v1/syringes/${id}`, {
    baseUrl,
    method: 'DELETE'
  });
  if (payload.statusCode >= 200 && payload.statusCode < 300) {
    return { ok: true, data: null, status: payload.statusCode };
  }
  return fail(
    payload.statusCode,
    fieldErrorsFrom(payload),
    genericErrorMessage(payload, 'Unable to delete syringe.'),
    remainingIuFrom(payload)
  );
}

export async function restockSyringe(
  id: string,
  count: number,
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}/restock`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ count })
  });
  return asResult(payload, 'Unable to restock syringes.');
}

export async function burnSyringe(
  id: string,
  count: number,
  baseUrl = ''
): Promise<DomainResult<Syringe>> {
  const payload = await readAction<Syringe>(`/api/v1/syringes/${id}/burn`, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ count })
  });
  return asResult(payload, 'Unable to burn syringes.');
}

export function parseCountInput(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '' || !/^\d+$/.test(trimmed)) {
    return null;
  }
  const value = Number(trimmed);
  if (!Number.isInteger(value) || value <= 0) {
    return null;
  }

  return value;
}

export function defaultSyringeId(syringes: Syringe[], lastUsedId: string | null): string {
  if (lastUsedId !== null && syringes.some((item) => item.id === lastUsedId)) {
    return lastUsedId;
  }
  const flagged = syringes.find((item) => item.is_default);
  return flagged?.id ?? syringes[0]?.id ?? '';
}

export function vialLabel(vial: { name: string; peptide_type_name: string }): string {
  if (vial.name === vial.peptide_type_name) {
    return vial.name;
  }
  return `${vial.name} · ${vial.peptide_type_name}`;
}

export function defaultOpenVialId(vials: { id: string }[], preferredId: string | null): string {
  if (preferredId !== null && vials.some((item) => item.id === preferredId)) {
    return preferredId;
  }
  return vials[0]?.id ?? '';
}
