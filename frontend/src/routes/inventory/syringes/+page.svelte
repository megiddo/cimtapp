<script lang="ts">
  import { onMount } from 'svelte';
  import { fetchSyringes, type Syringe } from '$lib/inventory';
  import { groupStock, syringeDetailHref } from '$lib/stock/inventoryFull';
  import InventoryFullList from '$lib/ui/InventoryFullList.svelte';

  let open = $state<Syringe[]>([]);
  let archived = $state<Syringe[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    const grouped = groupStock(await fetchSyringes('', 'all'));
    open = grouped.open;
    archived = grouped.archived;
    loaded = true;
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <InventoryFullList {open} {archived} hrefFor={(item) => syringeDetailHref(item.id)}>
    {#snippet row(syringe: Syringe)}
      <div>
        <strong>{syringe.label}</strong>
        {#if syringe.archived_at === null && syringe.is_default}
          <span class="badge">Default</span>
        {/if}
      </div>
      <div>{syringe.quantity} remaining</div>
      <div class="muted">{syringe.volume_ml} mL · {syringe.capacity_iu} IU</div>
    {/snippet}
  </InventoryFullList>
{/if}
