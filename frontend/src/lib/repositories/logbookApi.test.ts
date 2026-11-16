import { beforeEach, describe, expect, it, vi } from 'vitest';
import * as actions from '../api/actions';
import { createPerson, fetchPeople, fetchRemovedPeople, patchPerson, removePerson, restorePerson } from './people';
import { createPeptide, fetchPeptides, removePeptide } from './peptides';
import { fetchAccountSyringe, putAccountSyringe } from './syringe';
import { fetchCurrentVials } from './vials';
import {
  buildCreateLogBody,
  createLogEntry,
  deleteLogEntry,
  fetchLogDefaults,
  fetchLogEntries,
  fetchLogEntry,
  patchLogEntry
} from './log';

describe('logbook repositories', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('fetches people', async () => {
    vi.spyOn(actions, 'readAction').mockResolvedValue({ statusCode: 200, data: [{ id: 'p1', name: 'Noah' }] });
    const rows = await fetchPeople();
    expect(rows).toHaveLength(1);
  });

  it('creates a person', async () => {
    vi.spyOn(actions, 'readAction').mockResolvedValue({ statusCode: 201, data: { id: 'p1', name: 'Noah' } });
    const result = await createPerson({ name: 'Noah' });
    expect(result.ok).toBe(true);
  });

  it('fetches current vials', async () => {
    vi.spyOn(actions, 'readAction').mockResolvedValue({ statusCode: 200, data: [] });
    await expect(fetchCurrentVials()).resolves.toEqual([]);
  });

  it('fetches log defaults and list', async () => {
    const read = vi
      .spyOn(actions, 'readAction')
      .mockResolvedValueOnce({ statusCode: 200, data: { person_id: 'p1', logged_at: '', iu: null, source: null } })
      .mockResolvedValueOnce({ statusCode: 200, data: [] });
    await fetchLogDefaults('p1');
    await fetchLogEntries('p1', { limit: 5 });
    expect(read).toHaveBeenCalledTimes(2);
  });

  it('manages people lifecycle', async () => {
    const read = vi.spyOn(actions, 'readAction');
    read
      .mockResolvedValueOnce({ statusCode: 200, data: [] })
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'p1', name: 'Pat' } })
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'p1', name: 'Pat' } })
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'p1', name: 'Pat' } });
    await fetchRemovedPeople();
    expect((await patchPerson('p1', { name: 'Pat' })).ok).toBe(true);
    expect((await removePerson('p1')).ok).toBe(true);
    expect((await restorePerson('p1')).ok).toBe(true);
  });

  it('manages peptides and syringe', async () => {
    vi.spyOn(actions, 'readAction')
      .mockResolvedValueOnce({ statusCode: 200, data: [{ id: 't1', name: 'Tirzepatide' }] })
      .mockResolvedValueOnce({ statusCode: 201, data: { id: 'c1', name: 'Custom' } })
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'c1', name: 'Custom' } })
      .mockResolvedValueOnce({ statusCode: 200, data: { volume_ml: 0.5, capacity_iu: 50 } })
      .mockResolvedValueOnce({ statusCode: 200, data: { volume_ml: 0.3, capacity_iu: 30 } });
    await expect(fetchPeptides()).resolves.toHaveLength(1);
    expect((await createPeptide({ name: 'Custom' })).ok).toBe(true);
    expect((await removePeptide('c1')).ok).toBe(true);
    await expect(fetchAccountSyringe()).resolves.toEqual({ volume_ml: 0.5, capacity_iu: 50 });
    expect((await putAccountSyringe({ volume_ml: 0.3, capacity_iu: 30 })).ok).toBe(true);
  });

  it('covers log read/write helpers', async () => {
    vi.spyOn(actions, 'readAction')
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'e1', iu: 10 } as never })
      .mockResolvedValueOnce({ statusCode: 201, data: { id: 'e1' } as never })
      .mockResolvedValueOnce({ statusCode: 200, data: { id: 'e1' } as never })
      .mockResolvedValueOnce({ statusCode: 204, data: null });
    await fetchLogEntry('e1');
    const body = buildCreateLogBody({
      person_id: 'p1',
      vial_id: 'v1',
      iu: 10,
      logged_at: '2026-11-09T09:00',
      is_last_draw: false
    });
    expect((await createLogEntry(body)).ok).toBe(true);
    expect((await patchLogEntry('e1', body)).ok).toBe(true);
    expect((await deleteLogEntry('e1')).ok).toBe(true);
  });
});