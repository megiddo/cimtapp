import { apiFetch } from '../api';

export const USER_EXPORT_PATH = '/api/v1/me/export';
export const USER_EXPORT_FILENAME = 'peptrack-export.sqlite';

export function triggerBlobDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export async function downloadUserSqlite(
  baseUrl = '',
  save: (blob: Blob, filename: string) => void = triggerBlobDownload
): Promise<{ ok: true } | { ok: false; message: string }> {
  const response = await apiFetch(USER_EXPORT_PATH, { baseUrl });
  if (!response.ok) {
    return { ok: false, message: 'Unable to download your data.' };
  }
  save(await response.blob(), USER_EXPORT_FILENAME);
  return { ok: true };
}
