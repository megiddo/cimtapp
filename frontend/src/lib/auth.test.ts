import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  googleStartUrl,
  isMe,
  logout,
  normalizeEmail,
  PASSWORD_MIN_LENGTH,
  readAction,
  setPassword,
  submitCredentials,
  USER_EXPORT_FILENAME,
  downloadUserSqlite,
  triggerBlobDownload
} from './auth';

describe('auth helpers', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('normalizes email and keeps the Google start path as a full-page URL', () => {
    expect(normalizeEmail('  Foo@Example.COM  ')).toBe('foo@example.com');
    expect(googleStartUrl()).toBe('/api/v1/auth/google/start');
    expect(googleStartUrl('http://localhost:24780/')).toBe(
      'http://localhost:24780/api/v1/auth/google/start'
    );
    expect(PASSWORD_MIN_LENGTH).toBe(12);
  });

  it('rejects me payloads that leak DEK material', () => {
    expect(
      isMe({ email: 'a@b.c', has_password: true, has_google: false, remainder: null })
    ).toBe(true);
    expect(isMe(null)).toBe(false);
    expect(isMe({ email: 'a@b.c', has_password: true })).toBe(false);
    expect(
      isMe({
        email: 'a@b.c',
        has_password: true,
        has_google: false,
        encrypted_dek: 'nope'
      })
    ).toBe(false);
    expect(
      isMe({
        email: 'a@b.c',
        has_password: true,
        has_google: false,
        dek: 'nope'
      })
    ).toBe(false);
  });

  it('registers and returns me on 201', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 201,
        json: async () => ({
          statusCode: 201,
          data: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
        })
      })
    );

    await expect(submitCredentials('register', '  A@B.C ', 'twelvechars!!')).resolves.toEqual({
      ok: true,
      status: 201,
      me: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
    });
  });

  it('maps 401 login to generic copy and 422 to field errors', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        json: async () => ({
          statusCode: 401,
          error: { type: 'UNAUTHENTICATED', description: 'Invalid email or password' }
        })
      })
    );
    await expect(submitCredentials('login', 'a@b.c', 'wrong-password')).resolves.toMatchObject({
      ok: false,
      status: 401,
      message: 'Invalid email or password'
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({
          statusCode: 422,
          error: {
            type: 'VALIDATION_ERROR',
            description: 'Validation failed.',
            fields: { password: ['Password must be at least 12 characters.'] }
          }
        })
      })
    );
    const failed = await submitCredentials('register', 'a@b.c', 'short');
    expect(failed.ok).toBe(false);
    if (!failed.ok) {
      expect(failed.fields.password[0]).toContain('12');
    }

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 503,
        json: async () => ({
          statusCode: 503,
          error: { type: 'SERVICE_UNAVAILABLE', description: '' }
        })
      })
    );
    await expect(submitCredentials('login', 'a@b.c', 'twelvechars!!')).resolves.toMatchObject({
      ok: false,
      status: 503,
      message: 'Unable to sign in.'
    });
  });

  it('logs out and sets a password', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { ok: true } })
      })
    );
    await expect(logout()).resolves.toBe(true);

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({
          statusCode: 200,
          data: { email: 'a@b.c', has_password: true, has_google: true, remainder: null }
        })
      })
    );
    await expect(setPassword('twelvechars!!')).resolves.toMatchObject({ ok: true });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({ statusCode: 422, error: { type: 'VALIDATION_ERROR', description: '' } })
      })
    );
    await expect(setPassword('short')).resolves.toMatchObject({ ok: false, status: 422 });
  });

  it('readAction survives invalid JSON', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 503,
        json: async () => {
          throw new Error('no json');
        }
      })
    );
    await expect(readAction('/api/v1/me')).resolves.toEqual({ statusCode: 503 });
  });

  it('downloads the logged-in sqlite export', async () => {
    const blob = new Blob(['SQLite format 3'], { type: 'application/octet-stream' });
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        blob: async () => blob
      })
    );
    const save = vi.fn();
    await expect(downloadUserSqlite('', save)).resolves.toEqual({ ok: true });
    expect(save).toHaveBeenCalledWith(blob, USER_EXPORT_FILENAME);

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        blob: async () => new Blob()
      })
    );
    await expect(downloadUserSqlite('', save)).resolves.toEqual({
      ok: false,
      message: 'Unable to download your data.'
    });
  });

  it('triggers a blob download via a temporary anchor', () => {
    const click = vi.fn();
    const remove = vi.fn();
    const createObjectURL = vi.fn(() => 'blob:peptrack');
    const revokeObjectURL = vi.fn();
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });
    vi.spyOn(document, 'createElement').mockReturnValue({
      href: '',
      download: '',
      click,
      remove
    } as unknown as HTMLAnchorElement);
    vi.spyOn(document.body, 'appendChild').mockImplementation((node) => node);

    triggerBlobDownload(new Blob(['sqlite']), 'peptrack-export.sqlite');
    expect(createObjectURL).toHaveBeenCalled();
    expect(click).toHaveBeenCalled();
    expect(remove).toHaveBeenCalled();
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:peptrack');
  });
});
