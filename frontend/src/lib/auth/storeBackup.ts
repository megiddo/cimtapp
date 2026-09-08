import { readAction } from '../api/actions';
import { genericErrorMessage } from '../payload';

export const USER_STORE_BACKUP_PATH = '/api/v1/me/store-backup';
export const USER_STORE_BACKUP_RESTORE_PATH = '/api/v1/me/store-backup/restore';

export async function fetchStoreBackup(baseUrl = ''): Promise<{ available: boolean }> {
  const payload = await readAction<{ available: boolean }>(USER_STORE_BACKUP_PATH, { baseUrl });
  return { available: payload.data?.available === true };
}

export async function restoreStoreBackup(
  baseUrl = ''
): Promise<{ ok: true } | { ok: false; message: string }> {
  const payload = await readAction<{ ok: boolean }>(USER_STORE_BACKUP_RESTORE_PATH, {
    baseUrl,
    method: 'POST'
  });
  if (payload.statusCode >= 200 && payload.statusCode < 300 && payload.data?.ok === true) {
    return { ok: true };
  }
  return { ok: false, message: genericErrorMessage(payload, 'Unable to restore the backup.') };
}
