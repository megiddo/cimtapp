import { describe, expect, it } from 'vitest';
import { logHref } from './logHref';

describe('logHref', () => {
  it('returns the bare log route when no seeds are set', () => {
    expect(logHref()).toBe('/use/new');
    expect(logHref({ profileId: '', compoundId: '', iu: '' })).toBe('/use/new');
  });

  it('appends only provided seeds', () => {
    expect(logHref({ profileId: 'p1' })).toBe('/use/new?profile_id=p1');
    expect(logHref({ compoundId: 'c1', iu: 25 })).toBe('/use/new?compound_id=c1&iu=25');
    expect(logHref({ profileId: 'p1', compoundId: 'c1', iu: '12.5' })).toBe(
      '/use/new?profile_id=p1&compound_id=c1&iu=12.5'
    );
  });
});
