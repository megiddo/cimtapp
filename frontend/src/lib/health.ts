export type HealthStatusValue = 'ok' | 'degraded';

export type HealthResponse = {
  status: HealthStatusValue;
};

export function isHealthy(payload: HealthResponse): boolean {
  return payload.status === 'ok';
}

export function parseHealthStatus(value: string): HealthStatusValue {
  if (value === 'ok' || value === 'degraded') {
    return value;
  }

  throw new Error(`Invalid health status: ${value}`);
}

export function isHealthResponse(value: unknown): value is HealthResponse {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const status = (value as { status?: unknown }).status;
  return status === 'ok' || status === 'degraded';
}

export function healthUrl(baseUrl = ''): string {
  const trimmed = baseUrl.replace(/\/+$/, '');
  return `${trimmed}/api/v1/health`;
}

export async function fetchHealth(baseUrl = ''): Promise<HealthResponse> {
  const response = await fetch(healthUrl(baseUrl), { credentials: 'include' });
  if (!response.ok) {
    throw new Error(`Health check failed: ${response.status}`);
  }

  const body: unknown = await response.json();
  if (!isHealthResponse(body)) {
    throw new Error('Health check returned an unexpected payload');
  }

  return body;
}
