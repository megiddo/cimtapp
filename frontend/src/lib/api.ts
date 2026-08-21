export type ApiFetchInit = RequestInit & { baseUrl?: string };

export function apiUrl(path: string, baseUrl = ''): string {
  const trimmedBase = baseUrl.replace(/\/+$/, '');
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${trimmedBase}${normalizedPath}`;
}

export async function apiFetch(path: string, init: ApiFetchInit = {}): Promise<Response> {
  const { baseUrl = '', credentials: _ignored, ...rest } = init;
  return fetch(apiUrl(path, baseUrl), {
    ...rest,
    credentials: 'include'
  });
}

export async function apiJson<T>(path: string, init: ApiFetchInit = {}): Promise<T> {
  const response = await apiFetch(path, init);
  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }

  return (await response.json()) as T;
}
