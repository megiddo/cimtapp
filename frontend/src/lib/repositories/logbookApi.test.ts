import { beforeEach, describe, expect, it, vi } from 'vitest';
import * as actions from '../api/actions';
import { createPerson, fetchPeople } from './people';
import { fetchCurrentVials } from './vials';
import { fetchLogDefaults, fetchLogEntries } from './log';

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
    await fetchLogEntries('p1');
    expect(read).toHaveBeenCalledTimes(2);
  });
});