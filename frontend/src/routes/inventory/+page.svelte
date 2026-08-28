<script lang="ts">
  import { onMount } from 'svelte';
  import { formatMg, formatMl } from '$lib/dose';
  import {
    fetchBacBottles,
    fetchCompounds,
    fetchSyringes,
    vialLabel,
    type BacBottle,
    type Compound,
    type Syringe
  } from '$lib/inventory';

  let compounds = $state<Compound[]>([]);
  let bottles = $state<BacBottle[]>([]);
  let syringes = $state<Syringe[]>([]);
  let loaded = $state(false);
  let chooserOpen = $state(false);

  onMount(async () => {
    compounds = await fetchCompounds();
    bottles = await fetchBacBottles();
    syringes = await fetchSyringes();
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
  <section>
    <h2 class="day-heading">Vials</h2>
    {#if compounds.length === 0}
      <div class="empty-state">
        <p>No vials in inventory.</p>
      </div>
    {:else}
      <div class="cards">
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
      </div>
    {/if}
  </section>

  <section>
    <h2 class="day-heading">Bacteriostatic water</h2>
    {#if bottles.length === 0}
      <div class="empty-state">
        <p>No BAC bottles on hand. Mixes deduct from the current bottle.</p>
      </div>
    {:else}
      <div class="cards">
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
      </div>
    {/if}
  </section>

  <section>
    <h2 class="day-heading">Syringes</h2>
    {#if syringes.length === 0}
      <div class="empty-state">
        <p>No syringe types yet.</p>
      </div>
    {:else}
      <div class="cards">
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
      </div>
    {/if}
  </section>
{/if}

<div class="sticky-cta">
  <button type="button" onclick={openChooser}>Add to Inventory</button>
</div>

{#if chooserOpen}
  <div class="modal-backdrop">
    <button type="button" class="modal-backdrop-hit" aria-label="Close" onclick={closeChooser}></button>
    <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="add-inventory-title" tabindex="-1">
      <h2 id="add-inventory-title" class="day-heading">Add to inventory</h2>
      <a class="chooser-option" href="/inventory/new">Vials</a>
      <a class="chooser-option" href="/inventory/water/new">BAC</a>
      <a class="chooser-option" href="/inventory/syringes/new">Syringe</a>
      <button type="button" class="secondary" onclick={closeChooser}>Cancel</button>
    </div>
  </div>
{/if}
