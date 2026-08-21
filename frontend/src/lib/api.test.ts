import { afterEach, describe, expect, it, vi } from 'vitest';
import { apiFetch, apiJson, apiUrl } from './api';

describe('apiUrl', () => {
  it('avoids a double slash and prefixes a relative path', () => {
    expect(apiUrl('/api/v1/health')).toBe('/api/v1/health');
    expect(apiUrl('api/v1/health')).toBe('/api/v1/health');
    expect(apiUrl('/api/v1/health', 'http://localhost:8080')).toBe(
      'http://localhost:8080/api/v1/health'
    );
    expect(apiUrl('/api/v1/health', 'http://localhost:8080/')).toBe(
      'http://localhost:8080/api/v1/health'
    );
  });
});

describe('apiFetch', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('always sends credentials include, even if the caller omitted them', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true });
    vi.stubGlobal('fetch', fetchMock);

    await apiFetch('/api/v1/health', {
      baseUrl: 'http://localhost:8080',
      method: 'GET',
      credentials: 'omit'
    });

    expect(fetchMock).toHaveBeenCalledWith('http://localhost:8080/api/v1/health', {
      method: 'GET',
      credentials: 'include'
    });
  });
});

describe('apiJson', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('parses JSON on success', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ status: 'ok' })
      })
    );

    await expect(apiJson<{ status: string }>('/api/v1/health')).resolves.toEqual({ status: 'ok' });
  });

  it('throws the HTTP status when not ok', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        json: async () => ({})
      })
    );

    await expect(apiJson('/api/v1/me')).rejects.toThrow('Request failed: 401');
  });
});
