import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '../offline';
import type { DomainResult } from '../domain/result';
import type { FieldMap } from '../payload';

export type MutationOutcome<T> =
  | { kind: 'ok'; data: T; status: number }
  | { kind: 'fail'; status: number; fields: FieldMap; message: string; remainingIu: number | null }
  | { kind: 'offline'; message: string };

export async function runDomainMutation<T>(mutate: () => Promise<DomainResult<T>>): Promise<MutationOutcome<T>> {
  try {
    const result = await saveWhileOnline(mutate);
    if (result.ok) {
      return { kind: 'ok', data: result.data, status: result.status };
    }
    return {
      kind: 'fail',
      status: result.status,
      fields: result.fields,
      message: result.message,
      remainingIu: result.remainingIu
    };
  } catch {
    return { kind: 'offline', message: OFFLINE_SAVE_MESSAGE };
  }
}
