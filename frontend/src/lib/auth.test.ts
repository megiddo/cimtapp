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
  fetchStoreBackup,
  restoreStoreBackup,
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
    expect(isMe(undefined)).toBe(false);
    expect(isMe('a@b.c')).toBe(false);
    expect(isMe([])).toBe(false);
    expect(isMe({ email: 'a@b.c', has_password: true })).toBe(false);
    expect(isMe({ email: 1, has_password: true, has_google: false })).toBe(false);
    expect(isMe({ email: 'a@b.c', has_password: 'yes', has_google: false })).toBe(false);
    expect(isMe({ email: 'a@b.c', has_password: true, has_google: 'no' })).toBe(false);
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
        dek_nonce: 'nope'
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
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 201,
      json: async () => ({
        statusCode: 201,
        data: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
      })
    });
    vi.stubGlobal('fetch', fetchMock);

    await expect(submitCredentials('register', '  A@B.C ', 'twelvechars!!', 'http://app.test')).resolves.toEqual({
      ok: true,
      status: 201,
      me: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
    });
    expect(fetchMock.mock.calls[0][0]).toBe('http://app.test/api/v1/auth/register');
    expect(fetchMock.mock.calls[0][1]).toMatchObject({
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
    expect(JSON.parse(String(fetchMock.mock.calls[0][1].body))).toEqual({
      email: 'a@b.c',
      password: 'twelvechars!!'
    });

    const loginMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        statusCode: 200,
        data: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
      })
    });
    vi.stubGlobal('fetch', loginMock);
    await expect(submitCredentials('login', 'a@b.c', 'twelvechars!!')).resolves.toMatchObject({ ok: true, status: 200 });
    expect(loginMock.mock.calls[0][0]).toBe('/api/v1/auth/login');

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { email: 'a@b.c' } })
      })
    );
    await expect(submitCredentials('login', 'a@b.c', 'twelvechars!!')).resolves.toMatchObject({
      ok: false,
      status: 200,
      message: 'Unable to sign in.'
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 199,
        json: async () => ({
          statusCode: 199,
          data: { email: 'a@b.c', has_password: true, has_google: false, remainder: null }
        })
      })
    );
    await expect(submitCredentials('register', 'a@b.c', 'twelvechars!!')).resolves.toMatchObject({
      ok: false,
      status: 199
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
      expect(failed.message).toBe('Validation failed.');
    }

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({
          statusCode: 422,
          error: { type: 'VALIDATION_ERROR', description: '' }
        })
      })
    );
    await expect(submitCredentials('register', 'a@b.c', 'short')).resolves.toMatchObject({
      ok: false,
      message: 'Check the highlighted fields.'
    });

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
    const logoutMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ statusCode: 200, data: { ok: true } })
    });
    vi.stubGlobal('fetch', logoutMock);
    await expect(logout('http://app.test')).resolves.toBe(true);
    expect(logoutMock.mock.calls[0][0]).toBe('http://app.test/api/v1/auth/logout');
    expect(logoutMock.mock.calls[0][1]).toMatchObject({ method: 'POST' });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 199,
        json: async () => ({ statusCode: 199, data: { ok: true } })
      })
    );
    await expect(logout()).resolves.toBe(false);
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 300,
        json: async () => ({ statusCode: 300, data: { ok: true } })
      })
    );
    await expect(logout()).resolves.toBe(false);

    const setMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        statusCode: 200,
        data: { email: 'a@b.c', has_password: true, has_google: true, remainder: null }
      })
    });
    vi.stubGlobal('fetch', setMock);
    await expect(setPassword('twelvechars!!', 'http://app.test')).resolves.toMatchObject({ ok: true });
    expect(setMock.mock.calls[0][0]).toBe('http://app.test/api/v1/me/password');
    expect(JSON.parse(String(setMock.mock.calls[0][1].body))).toEqual({ password: 'twelvechars!!' });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        json: async () => ({ statusCode: 422, error: { type: 'VALIDATION_ERROR', description: '' } })
      })
    );
    await expect(setPassword('short')).resolves.toMatchObject({
      ok: false,
      status: 422,
      message: 'Unable to set password.'
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { email: 'a@b.c' } })
      })
    );
    await expect(setPassword('twelvechars!!')).resolves.toMatchObject({ ok: false, status: 200 });
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
    const save = vi.fn();
    const downloadMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      blob: async () => blob
    });
    vi.stubGlobal('fetch', downloadMock);
    await expect(downloadUserSqlite('http://app.test', save)).resolves.toEqual({ ok: true });
    expect(downloadMock.mock.calls[0][0]).toBe('http://app.test/api/v1/me/export');
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

  it('reads store-backup availability and restores it', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { available: true } })
      })
    );
    await expect(fetchStoreBackup()).resolves.toEqual({ available: true });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { available: false } })
      })
    );
    await expect(fetchStoreBackup()).resolves.toEqual({ available: false });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: {} })
      })
    );
    await expect(fetchStoreBackup()).resolves.toEqual({ available: false });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { available: 'true' } })
      })
    );
    await expect(fetchStoreBackup()).resolves.toEqual({ available: false });

    const backupMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ statusCode: 200, data: { available: true } })
    });
    vi.stubGlobal('fetch', backupMock);
    await expect(fetchStoreBackup('http://app.test')).resolves.toEqual({ available: true });
    expect(backupMock.mock.calls[0][0]).toBe('http://app.test/api/v1/me/store-backup');

    const restoreMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ statusCode: 200, data: { ok: true } })
    });
    vi.stubGlobal('fetch', restoreMock);
    await expect(restoreStoreBackup('http://app.test')).resolves.toEqual({ ok: true });
    expect(restoreMock.mock.calls[0][0]).toBe('http://app.test/api/v1/me/store-backup/restore');
    expect(restoreMock.mock.calls[0][1]).toMatchObject({ method: 'POST' });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ statusCode: 200, data: { ok: false } })
      })
    );
    await expect(restoreStoreBackup()).resolves.toEqual({
      ok: false,
      message: 'Unable to restore the backup.'
    });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 199,
        json: async () => ({ statusCode: 199, data: { ok: true } })
      })
    );
    await expect(restoreStoreBackup()).resolves.toMatchObject({ ok: false });

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 404,
        json: async () => ({ statusCode: 404, error: { type: 'RESOURCE_NOT_FOUND', description: '' } })
      })
    );
    await expect(restoreStoreBackup()).resolves.toEqual({
      ok: false,
      message: 'Unable to restore the backup.'
    });
  });

  it('triggers a blob download via a temporary anchor', () => {
    const click = vi.fn();
    const remove = vi.fn();
    const createObjectURL = vi.fn(() => 'blob:peptrack');
    const revokeObjectURL = vi.fn();
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });
    const link = {
      href: '',
      download: '',
      click,
      remove
    };
    vi.spyOn(document, 'createElement').mockReturnValue(link as unknown as HTMLAnchorElement);
    vi.spyOn(document.body, 'appendChild').mockImplementation((node) => node);

    triggerBlobDownload(new Blob(['sqlite']), 'peptrack-export.sqlite');
    expect(createObjectURL).toHaveBeenCalled();
    expect(link.href).toBe('blob:peptrack');
    expect(link.download).toBe('peptrack-export.sqlite');
    expect(click).toHaveBeenCalled();
    expect(remove).toHaveBeenCalled();
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:peptrack');
  });
});
