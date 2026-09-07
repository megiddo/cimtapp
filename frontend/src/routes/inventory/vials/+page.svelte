<script lang="ts">
  import { onMount } from 'svelte';
  import { formatMg, formatMl } from '$lib/dose';
  import { fetchCompounds, vialLabel, type Compound } from '$lib/inventory';
  import { groupStock, vialDetailHref } from '$lib/stock/inventoryFull';
  import InventoryFullList from '$lib/ui/InventoryFullList.svelte';

  let open = $state<Compound[]>([]);
  let archived = $state<Compound[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    const grouped = groupStock(await fetchCompounds('', 'all'));
    open = grouped.open;
    archived = grouped.archived;
    loaded = true;
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <InventoryFullList {open} {archived} hrefFor={(item) => vialDetailHref(item.id)}>
    {#snippet row(compound: Compound)}
      <div>
        <strong>{vialLabel(compound)}</strong>
        {#if compound.archived_at === null && compound.is_open}
          <span class="badge">Open</span>
        {/if}
      </div>
      <div>{formatMg(compound.remaining_mg)} mg · {formatMl(compound.remaining_ml)} mL remaining</div>
      <div class="muted">
        {formatMg(compound.peptide_mg)} mg / {formatMl(compound.bac_water_ml)} mL · {compound.compounded_at.replace('T', ' ')}
      </div>
    {/snippet}
  </InventoryFullList>
{/if}
