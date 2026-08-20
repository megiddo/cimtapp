import { describe, expect, it } from 'vitest';
import {
  isNetworkFailure,
  isOffline,
  OFFLINE_SAVE_MESSAGE,
  OfflineError,
  saveWhileOnline
} from './offline';

describe('offline save guard', () => {
  it('treats navigator.onLine false as offline', () => {
    expect(isOffline({ onLine: false })).toBe(true);
    expect(isOffline({ onLine: true })).toBe(false);
    expect(isOffline(undefined)).toBe(false);
  });

  it('does not call save when offline and does not fake success', async () => {
    let called = false;
    await expect(
      saveWhileOnline(async () => {
        called = true;
        return 'saved';
      }, { onLine: false })
    ).rejects.toMatchObject({ name: 'OfflineError', message: OFFLINE_SAVE_MESSAGE });
    expect(called).toBe(false);
  });

  it('returns the save result when online', async () => {
    await expect(saveWhileOnline(async () => 42, { onLine: true })).resolves.toBe(42);
  });

  it('maps fetch TypeError to offline', async () => {
    await expect(
      saveWhileOnline(async () => {
        throw new TypeError('Failed to fetch');
      }, { onLine: true })
    ).rejects.toBeInstanceOf(OfflineError);
  });

  it('rethrows non-network errors', async () => {
    await expect(
      saveWhileOnline(async () => {
        throw new Error('422 from server');
      }, { onLine: true })
    ).rejects.toThrow('422 from server');
  });

  it('classifies network failures and rethrows OfflineError', async () => {
    expect(isNetworkFailure(new TypeError('Failed to fetch'))).toBe(true);
    expect(isNetworkFailure(new Error('NetworkError when attempting to fetch'))).toBe(true);
    expect(isNetworkFailure(new Error('Load failed'))).toBe(true);
    expect(isNetworkFailure(new Error('nope'))).toBe(false);
    expect(isNetworkFailure('nope')).toBe(false);

    await expect(
      saveWhileOnline(async () => {
        throw new OfflineError();
      }, { onLine: true })
    ).rejects.toBeInstanceOf(OfflineError);

    await expect(
      saveWhileOnline(async () => {
        throw new Error('boom');
      }, { onLine: false })
    ).rejects.toBeInstanceOf(OfflineError);
  });
});
