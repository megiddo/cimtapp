import { afterEach, describe, expect, it, vi } from 'vitest';
import { OFFLINE_SAVE_MESSAGE } from '../offline';
import { runDomainMutation } from './runDomainMutation';

describe('runDomainMutation', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('returns ok when the repository succeeds', async () => {
    await expect(runDomainMutation(async () => ({ ok: true, data: { id: 'c1' }, status: 201 }))).resolves.toEqual({
      kind: 'ok',
      data: { id: 'c1' },
      status: 201
    });
  });

  it('returns fail with fields when the repository rejects', async () => {
    await expect(
      runDomainMutation(async () => ({
        ok: false,
        status: 422,
        fields: { iu: ['too much'] },
        message: 'Check the highlighted fields.',
        remainingIu: 4
      }))
    ).resolves.toEqual({
      kind: 'fail',
      status: 422,
      fields: { iu: ['too much'] },
      message: 'Check the highlighted fields.',
      remainingIu: 4
    });
  });

  it('maps an offline save to the toast copy', async () => {
    vi.stubGlobal('navigator', { onLine: false });
    await expect(runDomainMutation(async () => ({ ok: true, data: { id: 'c1' }, status: 200 }))).resolves.toEqual({
      kind: 'offline',
      message: OFFLINE_SAVE_MESSAGE
    });
  });
});
