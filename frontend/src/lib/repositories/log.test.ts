import { describe, expect, it } from 'vitest';
import { buildCreateLogBody } from './log';

describe('buildCreateLogBody', () => {
  it('omits syringe fields and sends only log fields', () => {
    const body = buildCreateLogBody({
      person_id: 'p1',
      vial_id: 'v1',
      iu: 25,
      logged_at: '2026-11-16T10:00',
      is_last_draw: true,
      notes: ''
    });
    expect(body).toEqual({
      person_id: 'p1',
      vial_id: 'v1',
      iu: 25,
      logged_at: expect.any(String),
      is_last_draw: true,
      notes: null
    });
    expect(body).not.toHaveProperty('syringe_volume_ml');
  });
});