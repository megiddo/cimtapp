<script lang="ts">
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import LogList from '$lib/logbook/LogList.svelte';
  import type { LogEntry } from '$lib/logbook/types';
  import { fetchLogEntries } from '$lib/repositories/log';
  import { fetchPeople } from '$lib/repositories/people';

  let rows = $state<LogEntry[]>([]);
  let personName = $state('');
  let loaded = $state(false);
  let missingPerson = $state(false);

  async function load(personId: string) {
    missingPerson = false;
    personName = '';
    rows = [];
    loaded = false;
  const people = await fetchPeople();
    const person = people.find((item) => item.id === personId);
    if (person === undefined) {
      missingPerson = true;
      loaded = true;
      return;
    }
    personName = person.name;
    rows = await fetchLogEntries(personId, { limit: 100 });
    loaded = true;
  }

  onMount(() => {
    const personId = page.url.searchParams.get('person') ?? '';
    if (personId === '') {
      missingPerson = true;
      loaded = true;
      return;
    }
    void load(personId);
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if missingPerson}
  <p class="muted">Choose a person from Log to see their history.</p>
{:else if rows.length === 0}
  <p class="muted">No doses logged for {personName} yet.</p>
{:else}
  <p class="muted">Doses for {personName}</p>
  <LogList rows={rows} editHref={(id) => `/log/${id}`} />
{/if}