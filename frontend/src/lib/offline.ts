export const OFFLINE_SAVE_MESSAGE = 'You are offline. The use was not saved.';

export class OfflineError extends Error {
  constructor(message = OFFLINE_SAVE_MESSAGE) {
    super(message);
    this.name = 'OfflineError';
  }
}

export function isOffline(nav: { onLine?: boolean } | undefined = globalThis.navigator): boolean {
  return nav?.onLine === false;
}

export async function saveWhileOnline<T>(
  save: () => Promise<T>,
  nav: { onLine?: boolean } | undefined = globalThis.navigator
): Promise<T> {
  if (isOffline(nav)) {
    throw new OfflineError();
  }

  try {
    return await save();
  } catch (error) {
    if (error instanceof OfflineError) {
      throw error;
    }
    if (isOffline(nav) || isNetworkFailure(error)) {
      throw new OfflineError();
    }
    throw error;
  }
}

export function isNetworkFailure(error: unknown): boolean {
  if (!(error instanceof Error)) {
    return false;
  }
  const message = error.message.toLowerCase();
  return (
    error.name === 'TypeError' ||
    message.includes('failed to fetch') ||
    message.includes('networkerror') ||
    message.includes('load failed')
  );
}
