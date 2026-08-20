import { describe, expect, it } from 'vitest';
import {
  fieldErrorsFrom,
  firstFieldError,
  genericErrorMessage,
  isRecord,
  isUnauthenticated,
  isValidationError,
  parseActionPayload,
  parseFieldMap
} from './payload';

describe('parseActionPayload', () => {
  it('reads statusCode, data, and a 422 field map', () => {
    const payload = parseActionPayload<{ email: string }>(
      {
        statusCode: 422,
        error: {
          type: 'VALIDATION_ERROR',
          description: 'Validation failed.',
          fields: { email: ['Email is already registered.'] }
        }
      },
      200
    );

    expect(payload.statusCode).toBe(422);
    expect(isValidationError(payload)).toBe(true);
    expect(fieldErrorsFrom(payload)).toEqual({ email: ['Email is already registered.'] });
    expect(firstFieldError(fieldErrorsFrom(payload), 'email')).toBe('Email is already registered.');
    expect(firstFieldError(fieldErrorsFrom(payload), 'password')).toBeNull();
    expect(firstFieldError({ password: [] }, 'password')).toBeNull();
  });

  it('falls back to the HTTP status when statusCode is missing', () => {
    expect(parseActionPayload(null, 503).statusCode).toBe(503);
    expect(parseActionPayload('nope', 401).statusCode).toBe(401);
    expect(parseActionPayload({ data: { ok: true } }, 201).data).toEqual({ ok: true });
  });

  it('treats 401 as unauthenticated even without an error type', () => {
    const payload = parseActionPayload({ statusCode: 401 }, 401);
    expect(isUnauthenticated(payload)).toBe(true);
    expect(genericErrorMessage(payload, 'fallback')).toBe('fallback');
    expect(
      genericErrorMessage(
        parseActionPayload({ statusCode: 401, error: { type: 'UNAUTHENTICATED', description: 'Invalid email or password' } }),
        'fallback'
      )
    ).toBe('Invalid email or password');
  });

  it('ignores non-string field messages', () => {
    expect(parseFieldMap({ email: [1], password: ['ok'] })).toEqual({ password: ['ok'] });
    expect(parseFieldMap(null)).toBeUndefined();
    expect(parseFieldMap({})).toBeUndefined();
    expect(isRecord([])).toBe(false);
    expect(isRecord(null)).toBe(false);
    expect(isRecord({ a: 1 })).toBe(true);
  });

  it('defaults unknown error types and drops empty field maps', () => {
    const payload = parseActionPayload({
      statusCode: 500,
      error: { type: 1, description: 2, fields: { email: [1] } }
    });
    expect(payload.error?.type).toBe('SERVER_ERROR');
    expect(payload.error?.description).toBeNull();
    expect(payload.error?.fields).toBeUndefined();
  });
});
