import { describe, expect, it } from 'vitest';
import { canSubmitLog } from './canSubmitLog';

describe('canSubmitLog', () => {
  it('requires person, vial, and a positive IU with one decimal', () => {
    expect(canSubmitLog('', 'v1', '25')).toBe(false);
    expect(canSubmitLog('p1', '', '25')).toBe(false);
    expect(canSubmitLog('p1', 'v1', '')).toBe(false);
    expect(canSubmitLog('p1', 'v1', '0')).toBe(false);
    expect(canSubmitLog('p1', 'v1', '25.55')).toBe(false);
    expect(canSubmitLog('p1', 'v1', '25')).toBe(true);
  });
});