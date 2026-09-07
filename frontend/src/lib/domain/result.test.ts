import { describe, expect, it } from 'vitest';
import { asDeleteResult, asResult, fail, remainingIuFrom } from './result';

describe('domain result mapper', () => {
  it('keeps remaining_iu only when it is a finite number', () => {
    expect(remainingIuFrom({ error: { remaining_iu: 18 } })).toBe(18);
    expect(remainingIuFrom({ error: { remaining_iu: 0 } })).toBe(0);
    expect(remainingIuFrom({ error: { remaining_iu: Number.NaN } })).toBeNull();
    expect(remainingIuFrom({ error: { remaining_iu: Number.POSITIVE_INFINITY } })).toBeNull();
    expect(remainingIuFrom({ error: { remaining_iu: '18' } })).toBeNull();
    expect(remainingIuFrom({})).toBeNull();
  });

  it('requires 2xx plus a body for write success', () => {
    expect(asResult({ statusCode: 201, data: { id: 'c1' } }, 'Unable to mix vial.')).toEqual({
      ok: true,
      data: { id: 'c1' },
      status: 201
    });
    expect(asResult({ statusCode: 200 }, 'Unable to mix vial.')).toMatchObject({ ok: false, status: 200 });
    expect(asResult({ statusCode: 199, data: { id: 'c1' } }, 'Unable to mix vial.')).toMatchObject({
      ok: false,
      status: 199
    });
    expect(
      asResult(
        { statusCode: 422, error: { type: 'VALIDATION_ERROR', description: '', fields: { name: ['x'] } } },
        'Unable to mix vial.'
      )
    ).toMatchObject({ ok: false, message: 'Check the highlighted fields.' });
    expect(asResult({ statusCode: 500, error: { type: 'SERVER_ERROR', description: '' } }, 'Unable to mix vial.')).toMatchObject({
      ok: false,
      message: 'Unable to mix vial.'
    });
  });

  it('treats any 2xx delete as success even without a body', () => {
    expect(asDeleteResult({ statusCode: 204 }, 'Unable to delete vial.')).toEqual({
      ok: true,
      data: null,
      status: 204
    });
    expect(asDeleteResult({ statusCode: 200 }, 'Unable to delete vial.')).toMatchObject({ ok: true, status: 200 });
    expect(asDeleteResult({ statusCode: 199 }, 'Unable to delete vial.')).toMatchObject({
      ok: false,
      status: 199,
      message: 'Unable to delete vial.'
    });
    expect(asDeleteResult({ statusCode: 300 }, 'Unable to delete vial.')).toMatchObject({ ok: false, status: 300 });
  });

  it('builds an explicit failure', () => {
    expect(fail(422, { id: ['no'] }, 'no', 3)).toEqual({
      ok: false,
      status: 422,
      fields: { id: ['no'] },
      message: 'no',
      remainingIu: 3
    });
  });
});
