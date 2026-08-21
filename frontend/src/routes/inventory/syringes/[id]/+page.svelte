<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import { parseIuInput, syringeLabel } from '$lib/dose';
  import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '$lib/offline';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    burnSyringe,
    deleteSyringe,
    fetchSyringe,
    fetchSyringes,
    parseCountInput,
    patchSyringe,
    restockSyringe,
    type Syringe
  } from '$lib/inventory';

  let syringe = $state<Syringe | null>(null);
  let onlySyringe = $state(false);
  let volumeMl = $state('');
  let capacityIu = $state('');
  let count = $state('1');
  let pending = $state(false);
  let pendingStock = $state(false);
  let deleting = $state(false);
  let fields = $state<FieldMap>({});
  let formError = $state('');
  let stockMessage = $state('');
  let toast = $state('');
  let loaded = $state(false);

  const volume = $derived(parseIuInput(volumeMl));
  const capacity = $derived(parseIuInput(capacityIu));
  const stockCount = $derived(parseCountInput(count));
  const previewLabel = $derived(
    volume !== null && capacity !== null && volume > 0 && capacity > 0
      ? syringeLabel(volume, capacity)
      : ''
  );

  onMount(async () => {
    const id = page.params.id;
    if (id === undefined) {
      loaded = true;
      return;
    }
    const [item, all] = await Promise.all([fetchSyringe(id), fetchSyringes()]);
    syringe = item;
    onlySyringe = all.length < 2;
    if (item !== null) {
      volumeMl = String(item.volume_ml);
      capacityIu = String(item.capacity_iu);
    }
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    const target = syringe;
    if (target === null || volume === null || capacity === null) {
      return;
    }
    pending = true;
    fields = {};
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() =>
        patchSyringe(target.id, { volume_ml: volume, capacity_iu: capacity })
      );
      pending = false;
      if (result.ok) {
        await goto('/inventory');
        return;
      }
      fields = result.fields;
      formError = result.message;
    } catch {
      pending = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
  }

  async function onSetDefault() {
    const target = syringe;
    if (target === null) {
      return;
    }
    pending = true;
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() => patchSyringe(target.id, { is_default: true }));
      pending = false;
      if (result.ok) {
        syringe = result.data;
        return;
      }
      formError = result.message;
    } catch {
      pending = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
  }

  async function onStock(kind: 'restock' | 'use') {
    const target = syringe;
    if (target === null || stockCount === null) {
      return;
    }
    pendingStock = true;
    stockMessage = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() =>
        kind === 'restock' ? restockSyringe(target.id, stockCount) : burnSyringe(target.id, stockCount)
      );
      pendingStock = false;
      if (result.ok) {
        syringe = result.data;
        return;
      }
      stockMessage = result.message;
    } catch {
      pendingStock = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
  }

  async function onDelete() {
    const target = syringe;
    if (target === null || onlySyringe) {
      return;
    }
    const ok = window.confirm('Delete this syringe type? This cannot be undone.');
    if (!ok) {
      return;
    }
    deleting = true;
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() => deleteSyringe(target.id));
      deleting = false;
      if (result.ok) {
        await goto('/inventory');
        return;
      }
      formError = result.message;
    } catch {
      deleting = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
  }
</script>

{#if toast}
  <div class="toast" role="status">{toast}</div>
{/if}

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if syringe === null}
  <div class="empty-state">
    <p>Syringe not found.</p>
    <a class="chip" href="/inventory">Back to inventory</a>
  </div>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    {#if syringe.is_default}
      <p><span class="badge">Default</span></p>
    {/if}

    <label>
      Volume mL
      <input inputmode="decimal" bind:value={volumeMl} />
      {#if firstFieldError(fields, 'volume_ml')}
        <span class="field-error">{firstFieldError(fields, 'volume_ml')}</span>
      {/if}
    </label>
    <label>
      Capacity IU
      <input inputmode="decimal" bind:value={capacityIu} />
      {#if firstFieldError(fields, 'capacity_iu')}
        <span class="field-error">{firstFieldError(fields, 'capacity_iu')}</span>
      {/if}
    </label>
    {#if previewLabel}
      <p class="muted">Label: {previewLabel}</p>
    {/if}

    <p>{syringe.quantity} remaining</p>
    <div class="stock-actions">
      <input inputmode="numeric" aria-label="Count for {syringe.label}" bind:value={count} placeholder="1" />
      <button
        type="button"
        class="secondary stock"
        disabled={pendingStock || pending || deleting || stockCount === null}
        onclick={() => onStock('use')}>Use</button
      >
      <button
        type="button"
        class="secondary stock"
        disabled={pendingStock || pending || deleting || stockCount === null}
        onclick={() => onStock('restock')}>Restock</button
      >
    </div>
    {#if stockMessage}
      <p class="field-error">{stockMessage}</p>
    {/if}

    {#if !syringe.is_default}
      <button type="button" class="secondary" disabled={pending || deleting} onclick={onSetDefault}>
        Set default
      </button>
    {/if}

    {#if formError}
      <p class="field-error" role="alert">{formError}</p>
    {/if}

    <div class="sticky-cta">
      <button type="submit" disabled={pending || deleting || volume === null || capacity === null}>
        {pending ? 'Saving…' : 'Save'}
      </button>
    </div>
  </form>

  {#if onlySyringe}
    <p class="muted below-fold">Keep at least one syringe type.</p>
  {:else}
    <button class="danger" type="button" disabled={pending || deleting} onclick={onDelete}>
      {deleting ? 'Deleting…' : 'Delete from inventory'}
    </button>
  {/if}
{/if}
