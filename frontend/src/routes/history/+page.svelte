<script lang="ts">
  import { onMount } from 'svelte';
  import { formatDoseLine } from '$lib/dose';
  import { formatTime, groupUsesByLocalDay } from '$lib/history';
  import {
    fetchProfiles,
    fetchUses,
    vialLabel,
    type LoggedUse,
    type Profile
  } from '$lib/inventory';

  let uses = $state<LoggedUse[]>([]);
  let profiles = $state<Profile[]>([]);
  let filterId = $state('all');
  let loaded = $state(false);
  const groups = $derived(groupUsesByLocalDay(uses));
  const showProfileLabel = $derived(filterId === 'all' && profiles.length > 1);

  onMount(async () => {
    profiles = await fetchProfiles();
    uses = await fetchUses({ limit: 100 });
    loaded = true;
  });

  async function setFilter(id: string) {
    filterId = id;
    uses = await fetchUses(id === 'all' ? { limit: 100 } : { limit: 100, profile_id: id });
  }
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  {#if profiles.length > 1}
    <div class="chip-row" role="tablist" aria-label="History filter">
      <button
        type="button"
        class="chip"
        class:active={filterId === 'all'}
        onclick={() => setFilter('all')}>All</button
      >
      {#each profiles as profile (profile.id)}
        <button
          type="button"
          class="chip"
          class:active={filterId === profile.id}
          onclick={() => setFilter(profile.id)}>{profile.name}</button
        >
      {/each}
    </div>
  {/if}

  {#if uses.length === 0}
    <div class="empty-state">
      <p>No uses yet. Log a use from the Log tab.</p>
      <a class="chip" href="/use/new">Log use</a>
    </div>
  {:else}
    {#each groups as group (group.day)}
      <h2 class="day-heading">{group.heading}</h2>
      <div class="row-list">
        {#each group.uses as use (use.id)}
          <a class="row" href="/history/{use.id}">
            <span>
              <span class="primary">{formatTime(use.used_at)} · {formatDoseLine(use.iu, use.peptide_mg)}</span>
              <div class="secondary">
                {#if showProfileLabel && use.profile_name}
                  {use.profile_name} ·
                {/if}
                {vialLabel({ name: use.compound_name, peptide_type_name: use.peptide_type_name })} · {use.syringe_label ?? ''}
              </div>
            </span>
          </a>
        {/each}
      </div>
    {/each}
  {/if}
{/if}
