import { apiFetch } from '../api';
import { parseActionPayload, type ActionPayload } from '../payload';

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
