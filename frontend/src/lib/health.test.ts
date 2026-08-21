import { describe, expect, it, vi, afterEach } from 'vitest';
import {
  fetchHealth,
  healthUrl,
  isHealthResponse,
  isHealthy,
  parseHealthStatus
} from './health';

describe('health client', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('treats only ok as healthy', () => {
    expect(isHealthy({ status: 'ok' })).toBe(true);
    expect(isHealthy({ status: 'degraded' })).toBe(false);
  });

  it('parses known status strings and rejects others', () => {
    expect(parseHealthStatus('ok')).toBe('ok');
    expect(parseHealthStatus('degraded')).toBe('degraded');
    expect(() => parseHealthStatus('down')).toThrow(/Invalid health status: down/);
    expect(() => parseHealthStatus('')).toThrow(/Invalid health status/);
  });

  it('narrows JSON payloads', () => {
    expect(isHealthResponse({ status: 'ok' })).toBe(true);
    expect(isHealthResponse({ status: 'degraded' })).toBe(true);
    expect(isHealthResponse({ status: 'down' })).toBe(false);
    expect(isHealthResponse(null)).toBe(false);
    expect(isHealthResponse('ok')).toBe(false);
    expect(isHealthResponse({})).toBe(false);
  });

  it('builds the health URL without a double slash', () => {
    expect(healthUrl('')).toBe('/api/v1/health');
    expect(healthUrl('http://localhost:24780')).toBe('http://localhost:24780/api/v1/health');
    expect(healthUrl('http://localhost:24780/')).toBe('http://localhost:24780/api/v1/health');
  });

  it('fetches health with credentials and returns the payload', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: 'ok' })
    });
    vi.stubGlobal('fetch', fetchMock);

    await expect(fetchHealth('http://localhost:24780')).resolves.toEqual({ status: 'ok' });
    expect(fetchMock).toHaveBeenCalledWith('http://localhost:24780/api/v1/health', {
      credentials: 'include'
    });
  });

  it('throws when the HTTP status is not ok', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 503,
        json: async () => ({})
      })
    );

    await expect(fetchHealth()).rejects.toThrow('Health check failed: 503');
  });

  it('throws when the payload is not a health document', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ statusCode: 200 })
      })
    );

    await expect(fetchHealth()).rejects.toThrow('unexpected payload');
  });
});
