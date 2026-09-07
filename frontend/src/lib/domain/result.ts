import { fieldErrorsFrom, genericErrorMessage, isValidationError, type ActionPayload, type FieldMap } from '../payload';

export type DomainResult<T> =
  | { ok: true; data: T; status: number }
  | { ok: false; status: number; fields: FieldMap; message: string; remainingIu: number | null };

export function fail<T>(status: number, fields: FieldMap, message: string, remainingIu: number | null): DomainResult<T> {
  return { ok: false, status, fields, message, remainingIu };
}

export function remainingIuFrom(payload: { error?: { remaining_iu?: unknown } }): number | null {
  const value = payload.error?.remaining_iu;
  return typeof value === 'number' && Number.isFinite(value) ? value : null;
}

export function asResult<T>(payload: ActionPayload<T>, fallback: string): DomainResult<T> {
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

export function asDeleteResult(payload: ActionPayload<null>, fallback: string): DomainResult<null> {
  if (payload.statusCode >= 200 && payload.statusCode < 300) {
    return { ok: true, data: null, status: payload.statusCode };
  }
  return fail(payload.statusCode, fieldErrorsFrom(payload), genericErrorMessage(payload, fallback), remainingIuFrom(payload));
}
