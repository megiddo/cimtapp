import { describe, expect, it } from 'vitest';
import { probablyEmptyLabel } from './probablyEmpty';

describe('probablyEmptyLabel', () => {
  it('follows the API flag even when mg would suggest otherwise', () => {
    expect(probablyEmptyLabel(true)).toBe('Probably empty');
    expect(probablyEmptyLabel(false)).toBeNull();
  });
});