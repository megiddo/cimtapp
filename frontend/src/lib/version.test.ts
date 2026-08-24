import { describe, expect, it } from 'vitest';
import { APP_VERSION } from './version';

describe('APP_VERSION', () => {
  it('is a v-prefixed semver', () => {
    expect(APP_VERSION).toMatch(/^v\d+\.\d+\.\d+$/);
  });
});
