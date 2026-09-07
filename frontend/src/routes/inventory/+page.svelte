<script lang="ts">
  import { onMount } from 'svelte';
  import { formatMg, formatMl } from '$lib/dose';
  import { fetchBacBottles, fetchCompounds, fetchSyringes, vialLabel, type BacBottle, type Compound, type Syringe } from '$lib/inventory';
  import { openStockOnly } from '$lib/stock/inventorySheet';
  import { fullListHref } from '$lib/stock/routes';
  import InventorySection from '$lib/ui/InventorySection.svelte';
  import ModalSheet from '$lib/ui/ModalSheet.svelte';

  let compounds = $state<Compound[]>([]);
  let bottles = $state<BacBottle[]>([]);
  let syringes = $state<Syringe[]>([]);
  let loaded = $state(false);
  let chooserOpen = $state(false);

  onMount(async () => {
    compounds = openStockOnly(await fetchCompounds());
    bottles = openStockOnly(await fetchBacBottles());
    syringes = openStockOnly(await fetchSyringes());
    loaded = true;
  });

  function openChooser() {
    chooserOpen = true;
  }

  function closeChooser() {
    chooserOpen = false;
  }

  function onChooserKey(event: KeyboardEvent) {
    if (chooserOpen && event.key === 'Escape') {
      closeChooser();
    }
  }
</script>

<svelte:window onkeydown={onChooserKey} />

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <InventorySection title="Vials" fullListHref={fullListHref('vials')} empty="No vials in inventory." count={compounds.length}>
    {#each compounds as compound (compound.id)}
      <a class="card" href="/inventory/{compound.id}">
        <div>
          <strong>{vialLabel(compound)}</strong>
          {#if compound.is_open}
            <span class="badge">Open</span>
          {/if}
        </div>
        <div>{formatMg(compound.remaining_mg)} mg · {formatMl(compound.remaining_ml)} mL remaining</div>
        <div class="muted">
          {formatMg(compound.peptide_mg)} mg / {formatMl(compound.bac_water_ml)} mL · {compound.compounded_at.replace('T', ' ')}
        </div>
      </a>
    {/each}
  </InventorySection>

  <InventorySection
    title="Bacteriostatic water"
    fullListHref={fullListHref('water')}
    empty="No BAC bottles on hand. Mixes deduct from the current bottle."
    count={bottles.length}
  >
    {#each bottles as bottle (bottle.id)}
      <a class="card" href="/inventory/water/{bottle.id}">
        <div>
          <strong>{formatMl(bottle.remaining_ml)} mL remaining</strong>
          {#if bottle.is_current}
            <span class="badge">Current</span>
          {/if}
        </div>
        <div class="muted">{formatMl(bottle.volume_ml)} mL bottle · {bottle.opened_at.replace('T', ' ')}</div>
      </a>
    {/each}
  </InventorySection>

  <InventorySection title="Syringes" fullListHref={fullListHref('syringes')} empty="No syringe types yet." count={syringes.length}>
    {#each syringes as syringe (syringe.id)}
      <a class="card" href="/inventory/syringes/{syringe.id}">
        <div>
          <strong>{syringe.label}</strong>
          {#if syringe.is_default}
            <span class="badge">Default</span>
          {/if}
        </div>
        <div>{syringe.quantity} remaining</div>
        <div class="muted">{syringe.volume_ml} mL · {syringe.capacity_iu} IU</div>
      </a>
    {/each}
  </InventorySection>
{/if}

<div class="sticky-cta">
  <button type="button" onclick={openChooser}>Add to Inventory</button>
</div>

{#if chooserOpen}
  <ModalSheet title="Add to inventory" titleId="add-inventory-title" onclose={closeChooser}>
    <a class="chooser-option" href="/inventory/new">Vials</a>
    <a class="chooser-option" href="/inventory/water/new">BAC</a>
    <a class="chooser-option" href="/inventory/syringes/new">Syringe</a>
    <button type="button" class="secondary" onclick={closeChooser}>Cancel</button>
  </ModalSheet>
{/if}
