<script lang="ts">
  import { onMount } from 'svelte';
  import { formatMg } from '$lib/dose';
  import { fetchCompounds, type Compound } from '$lib/inventory';

  let compounds = $state<Compound[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    compounds = await fetchCompounds();
    loaded = true;
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if compounds.length === 0}
  <p>No vials yet.</p>
{:else}
  <div class="cards">
    {#each compounds as compound, index (compound.id)}
      <article class="card">
        <div>
          <strong>{compound.peptide_type_name}</strong>
          {#if index === 0}
            <span class="badge">Current</span>
          {/if}
        </div>
        <div>{formatMg(compound.remaining_mg)} mg remaining</div>
        <div class="muted">{compound.compounded_at.replace('T', ' ')}</div>
      </article>
    {/each}
  </div>
{/if}

<div class="sticky-cta">
  <a href="/inventory/new">Mix vial</a>
</div>
