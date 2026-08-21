<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import { formatMl } from '$lib/dose';
  import { toDatetimeLocalValue } from '$lib/datetime';
  import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '$lib/offline';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    deleteBacBottle,
    fetchBacBottle,
    patchBacBottle,
    type BacBottle
  } from '$lib/inventory';

  let bottle = $state<BacBottle | null>(null);
  let openedAt = $state('');
  let notes = $state('');
  let pending = $state(false);
  let deleting = $state(false);
  let fields = $state<FieldMap>({});
  let formError = $state('');
  let toast = $state('');
  let loaded = $state(false);

  const unused = $derived(
    bottle !== null && Math.abs(bottle.remaining_ml - bottle.volume_ml) < 1e-9
  );

  onMount(async () => {
    const id = page.params.id;
    if (id === undefined) {
      loaded = true;
      return;
    }
    bottle = await fetchBacBottle(id);
    if (bottle !== null) {
      notes = bottle.notes ?? '';
      const parsed = new Date(bottle.opened_at);
      openedAt = Number.isNaN(parsed.getTime()) ? bottle.opened_at : toDatetimeLocalValue(parsed);
    }
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    const target = bottle;
    if (target === null) {
      return;
    }
    pending = true;
    fields = {};
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() =>
        patchBacBottle(target.id, {
          opened_at: openedAt,
          notes: notes === '' ? null : notes
        })
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

  async function onDelete() {
    const target = bottle;
    if (target === null || !unused) {
      return;
    }
    const ok = window.confirm('Delete this unused bottle? This cannot be undone.');
    if (!ok) {
      return;
    }
    deleting = true;
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() => deleteBacBottle(target.id));
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
{:else if bottle === null}
  <div class="empty-state">
    <p>Bottle not found.</p>
    <a class="chip" href="/inventory">Back to inventory</a>
  </div>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    <p>
      <strong>{formatMl(bottle.remaining_ml)} mL remaining</strong>
      {#if bottle.is_current}
        <span class="badge">Current</span>
      {/if}
    </p>
    <p class="muted">{formatMl(bottle.volume_ml)} mL bottle</p>

    <label>
      Opened at
      <input type="datetime-local" bind:value={openedAt} />
      {#if firstFieldError(fields, 'opened_at')}
        <span class="field-error">{firstFieldError(fields, 'opened_at')}</span>
      {/if}
    </label>

    <label>
      Notes
      <input type="text" bind:value={notes} />
    </label>

    {#if formError && !firstFieldError(fields, 'opened_at')}
      <p class="field-error" role="alert">{formError}</p>
    {/if}

    <div class="sticky-cta">
      <button type="submit" disabled={pending || deleting}>
        {pending ? 'Saving…' : 'Save'}
      </button>
    </div>
  </form>

  {#if unused}
    <button class="danger" type="button" disabled={pending || deleting} onclick={onDelete}>
      {deleting ? 'Deleting…' : 'Delete from inventory'}
    </button>
  {:else}
    <p class="muted below-fold">Delete is unavailable after this bottle has been used for a mix.</p>
  {/if}
{/if}
