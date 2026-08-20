import { apiUrl } from './api';
import { isMe, type Me } from './auth';
import { parseActionPayload } from './payload';

export type SessionProbe = {
  authenticated: boolean;
  me: Me | null;
  status: number;
};

export function isLoginPath(pathname: string): boolean {
  return pathname === '/login' || pathname.startsWith('/login/');
}

export function shouldRedirectToLogin(pathname: string, authenticated: boolean): boolean {
  return !authenticated && !isLoginPath(pathname);
}

export function shouldRedirectToHome(pathname: string, authenticated: boolean): boolean {
  return authenticated && isLoginPath(pathname);
}

export async function probeSession(
  fetchFn: typeof fetch = fetch,
  baseUrl = ''
): Promise<SessionProbe> {
  const response = await fetchFn(apiUrl('/api/v1/me', baseUrl), { credentials: 'include' });
  if (response.status === 401) {
    return { authenticated: false, me: null, status: 401 };
  }

  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    return { authenticated: false, me: null, status: response.status };
  }

  const payload = parseActionPayload<Me>(body, response.status);
  if (!response.ok || !isMe(payload.data)) {
    return { authenticated: false, me: null, status: response.status };
  }

  return { authenticated: true, me: payload.data, status: response.status };
}
