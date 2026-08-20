<script lang="ts">
  import { onMount } from 'svelte';
  import { formatDoseLine } from '$lib/dose';
  import { formatTime, groupUsesByLocalDay } from '$lib/history';
  import { fetchUses, type LoggedUse } from '$lib/inventory';

  let uses = $state<LoggedUse[]>([]);
  let loaded = $state(false);
  const groups = $derived(groupUsesByLocalDay(uses));

  onMount(async () => {
    uses = await fetchUses({ limit: 100 });
    loaded = true;
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if uses.length === 0}
  <p>No uses yet.</p>
{:else}
  {#each groups as group (group.day)}
    <h2 class="day-heading">{group.heading}</h2>
    <div class="row-list">
      {#each group.uses as use (use.id)}
        <a class="row" href="/history/{use.id}">
          <span>
            <span class="primary">{formatTime(use.used_at)} · {formatDoseLine(use.iu, use.peptide_mg)}</span>
            <div class="secondary">{use.peptide_type_name} · {use.syringe_label ?? ''}</div>
          </span>
        </a>
      {/each}
    </div>
  {/each}
{/if}
