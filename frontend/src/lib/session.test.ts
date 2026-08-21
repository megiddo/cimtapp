import { afterEach, describe, expect, it, vi } from 'vitest';
import { isLoginPath, probeSession, shouldRedirectToHome, shouldRedirectToLogin } from './session';

describe('session gating', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('treats only /login as the public auth route', () => {
    expect(isLoginPath('/login')).toBe(true);
    expect(isLoginPath('/login/reset')).toBe(true);
    expect(isLoginPath('/')).toBe(false);
    expect(isLoginPath('/settings')).toBe(false);
    expect(isLoginPath('/login-help')).toBe(false);
  });

  it('redirects app routes to login when unauthenticated', () => {
    expect(shouldRedirectToLogin('/', false)).toBe(true);
    expect(shouldRedirectToLogin('/inventory', false)).toBe(true);
    expect(shouldRedirectToLogin('/history', false)).toBe(true);
    expect(shouldRedirectToLogin('/use/new', false)).toBe(true);
    expect(shouldRedirectToLogin('/settings', false)).toBe(true);
    expect(shouldRedirectToLogin('/login', false)).toBe(false);
    expect(shouldRedirectToLogin('/', true)).toBe(false);
  });

  it('sends an authenticated visitor off the login page', () => {
    expect(shouldRedirectToHome('/login', true)).toBe(true);
    expect(shouldRedirectToHome('/login/x', true)).toBe(true);
    expect(shouldRedirectToHome('/', true)).toBe(false);
    expect(shouldRedirectToHome('/login', false)).toBe(false);
  });

  it('probes GET /me with credentials and treats 401 as signed out', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({})
    });
    await expect(probeSession(fetchMock)).resolves.toEqual({
      authenticated: false,
      me: null,
      status: 401
    });
    expect(fetchMock).toHaveBeenCalledWith('/api/v1/me', { credentials: 'include' });
  });

  it('returns me when GET /me succeeds', async () => {
    const me = { email: 'a@b.c', has_password: true, has_google: false, remainder: null };
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ statusCode: 200, data: me })
    });
    await expect(probeSession(fetchMock, 'http://localhost:8080')).resolves.toEqual({
      authenticated: true,
      me,
      status: 200
    });
    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8080/api/v1/me', {
      credentials: 'include'
    });
  });

  it('treats 503 and invalid JSON as signed out', async () => {
    await expect(
      probeSession(
        vi.fn().mockResolvedValue({
          ok: false,
          status: 503,
          json: async () => ({ statusCode: 503 })
        })
      )
    ).resolves.toMatchObject({ authenticated: false, status: 503 });

    await expect(
      probeSession(
        vi.fn().mockResolvedValue({
          ok: true,
          status: 200,
          json: async () => {
            throw new Error('bad json');
          }
        })
      )
    ).resolves.toMatchObject({ authenticated: false, status: 200 });
  });
});
