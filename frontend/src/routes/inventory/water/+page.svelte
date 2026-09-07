<script lang="ts">
  import { onMount } from 'svelte';
  import { formatMl } from '$lib/dose';
  import { fetchBacBottles, type BacBottle } from '$lib/inventory';
  import { groupStock, waterDetailHref } from '$lib/stock/inventoryFull';
  import InventoryFullList from '$lib/ui/InventoryFullList.svelte';

  let open = $state<BacBottle[]>([]);
  let archived = $state<BacBottle[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    const grouped = groupStock(await fetchBacBottles('', 'all'));
    open = grouped.open;
    archived = grouped.archived;
    loaded = true;
  });
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <InventoryFullList {open} {archived} hrefFor={(item) => waterDetailHref(item.id)}>
    {#snippet row(bottle: BacBottle)}
      <div>
        <strong>{formatMl(bottle.remaining_ml)} mL remaining</strong>
        {#if bottle.archived_at === null && bottle.is_current}
          <span class="badge">Current</span>
        {/if}
      </div>
      <div class="muted">{formatMl(bottle.volume_ml)} mL bottle · {bottle.opened_at.replace('T', ' ')}</div>
    {/snippet}
  </InventoryFullList>
{/if}
