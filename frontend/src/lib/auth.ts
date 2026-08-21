import { apiFetch, apiUrl } from './api';
import {
  fieldErrorsFrom,
  genericErrorMessage,
  isUnauthenticated,
  isValidationError,
  parseActionPayload,
  type ActionPayload,
  type FieldMap
} from './payload';

export const PASSWORD_MIN_LENGTH = 12;
export const GOOGLE_START_PATH = '/api/v1/auth/google/start';

export type Me = {
  email: string;
  has_password: boolean;
  has_google: boolean;
  remainder: null | unknown;
};

export type AuthResult =
  | { ok: true; me: Me; status: number }
  | { ok: false; status: number; fields: FieldMap; message: string };

export function googleStartUrl(baseUrl = ''): string {
  return apiUrl(GOOGLE_START_PATH, baseUrl);
}

export function normalizeEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function isMe(value: unknown): value is Me {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const record = value as Record<string, unknown>;
  return (
    typeof record.email === 'string' &&
    typeof record.has_password === 'boolean' &&
    typeof record.has_google === 'boolean' &&
    !('encrypted_dek' in record) &&
    !('dek_nonce' in record) &&
    !('dek' in record)
  );
}

export async function readAction<T>(
  path: string,
  init: RequestInit & { baseUrl?: string } = {}
): Promise<ActionPayload<T>> {
  const { baseUrl, ...rest } = init;
  const response = await apiFetch(path, { ...rest, baseUrl });
  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  return parseActionPayload<T>(body, response.status);
}

export async function submitCredentials(
  mode: 'login' | 'register',
  email: string,
  password: string,
  baseUrl = ''
): Promise<AuthResult> {
  const path = mode === 'register' ? '/api/v1/auth/register' : '/api/v1/auth/login';
  const payload = await readAction<Me>(path, {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: normalizeEmail(email), password })
  });

  if (payload.statusCode >= 200 && payload.statusCode < 300 && isMe(payload.data)) {
    return { ok: true, me: payload.data, status: payload.statusCode };
  }

  return {
    ok: false,
    status: payload.statusCode,
    fields: fieldErrorsFrom(payload),
    message: genericErrorMessage(
      payload,
      isValidationError(payload)
        ? 'Check the highlighted fields.'
        : isUnauthenticated(payload)
          ? 'Invalid email or password'
          : 'Unable to sign in.'
    )
  };
}

export async function logout(baseUrl = ''): Promise<boolean> {
  const payload = await readAction<{ ok: boolean }>('/api/v1/auth/logout', {
    baseUrl,
    method: 'POST'
  });
  return payload.statusCode >= 200 && payload.statusCode < 300;
}

export async function setPassword(password: string, baseUrl = ''): Promise<AuthResult> {
  const payload = await readAction<Me>('/api/v1/me/password', {
    baseUrl,
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ password })
  });

  if (payload.statusCode >= 200 && payload.statusCode < 300 && isMe(payload.data)) {
    return { ok: true, me: payload.data, status: payload.statusCode };
  }

  return {
    ok: false,
    status: payload.statusCode,
    fields: fieldErrorsFrom(payload),
    message: genericErrorMessage(payload, 'Unable to set password.')
  };
}
