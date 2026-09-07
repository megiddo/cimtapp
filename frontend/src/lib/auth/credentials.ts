import { apiUrl } from '../api';
import { readAction } from '../api/actions';
import { fieldErrorsFrom, genericErrorMessage, isUnauthenticated, isValidationError, type FieldMap } from '../payload';
import { isMe, normalizeEmail, type Me } from './me';

export const PASSWORD_MIN_LENGTH = 12;
export const GOOGLE_START_PATH = '/api/v1/auth/google/start';

export type AuthResult =
  | { ok: true; me: Me; status: number }
  | { ok: false; status: number; fields: FieldMap; message: string };

export function googleStartUrl(baseUrl = ''): string {
  return apiUrl(GOOGLE_START_PATH, baseUrl);
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
