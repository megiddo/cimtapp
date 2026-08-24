<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import {
    concentrationFromUse,
    FALLBACK_SYRINGE_CAPACITY_IU,
    FALLBACK_SYRINGE_VOLUME_ML,
    formatDoseLine,
    formatMg,
    parseIuInput,
    peptideMgAtConcentration
  } from '$lib/dose';
  import { toDatetimeLocalValue } from '$lib/datetime';
  import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '$lib/offline';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { deleteUse, fetchSyringes, fetchUse, patchUse, type LoggedUse, type Syringe } from '$lib/inventory';

  let original = $state<LoggedUse | null>(null);
  let syringes = $state<Syringe[]>([]);
  let iuText = $state('');
  let syringeId = $state('');
  let usedAt = $state('');
  let notes = $state('');
  let pending = $state(false);
  let deleting = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);
  let toast = $state('');
  let iuError = $state('');
  let formError = $state('');

  const iu = $derived(parseIuInput(iuText));
  const syringe = $derived(syringes.find((item) => item.id === syringeId) ?? null);
  const comparedMg = $derived.by(() => {
    if (original === null || iu === null) {
      return null;
    }
    const conc = concentrationFromUse(original.peptide_mg, original.volume_ml);
    if (conc === null) {
      return null;
    }
    return peptideMgAtConcentration(
      iu,
      conc,
      syringe?.volume_ml ?? FALLBACK_SYRINGE_VOLUME_ML,
      syringe?.capacity_iu ?? FALLBACK_SYRINGE_CAPACITY_IU
    );
  });

  onMount(async () => {
    const id = page.params.id;
    if (id === undefined) {
      loaded = true;
      return;
    }
    original = await fetchUse(id);
    syringes = await fetchSyringes();
    if (original !== null) {
      iuText = String(original.iu);
      syringeId = original.syringe_id ?? '';
      notes = original.notes ?? '';
      const parsed = new Date(original.used_at);
      usedAt = Number.isNaN(parsed.getTime()) ? original.used_at : toDatetimeLocalValue(parsed);
    }
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    const target = original;
    if (target === null || iu === null) {
      return;
    }
    pending = true;
    fields = {};
    toast = '';
    iuError = '';
    formError = '';
    try {
      const result = await saveWhileOnline(() =>
        patchUse(target.id, {
          iu,
          syringe_id: syringeId === '' ? null : syringeId,
          used_at: usedAt,
          notes: notes === '' ? null : notes
        })
      );
      pending = false;
      if (result.ok) {
        await goto('/history');
        return;
      }
      fields = result.fields;
      iuError = firstFieldError(result.fields, 'iu') ?? result.message;
    } catch {
      pending = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
  }

  async function onDelete() {
    const target = original;
    if (target === null) {
      return;
    }
    const ok = window.confirm('Delete this use? Remainder on the mix will increase.');
    if (!ok) {
      return;
    }
    deleting = true;
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() => deleteUse(target.id));
      deleting = false;
      if (result.ok) {
        await goto('/history');
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
  <p class="toast" role="status">{toast}</p>
{/if}

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if original === null}
  <p>Use not found.</p>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    <label>
      IU
      <input inputmode="decimal" name="iu" bind:value={iuText} disabled={pending || deleting} />
      {#if iuError}
        <span class="field-error">{iuError}</span>
      {/if}
    </label>

    <p class="preview">
      {formatDoseLine(original.iu, original.peptide_mg)}
      {#if comparedMg !== null}
        → {formatMg(comparedMg)} mg
      {/if}
    </p>

    <label>
      Syringe
      <select bind:value={syringeId}>
        <option value="">None</option>
        {#each syringes as item (item.id)}
          <option value={item.id}>{item.label}</option>
        {/each}
      </select>
    </label>

    <div class="below-fold">
      <label>
        Used at
        <input type="datetime-local" bind:value={usedAt} />
      </label>
      <label>
        Notes
        <input type="text" bind:value={notes} />
      </label>
    </div>

    {#if formError}
      <p class="field-error" role="alert">{formError}</p>
    {/if}

    <div class="sticky-cta">
      <button type="submit" disabled={pending || deleting || !loaded || iu === null}>{pending ? 'Saving…' : 'Save'}</button>
    </div>
  </form>

  <button class="danger" type="button" disabled={pending || deleting} onclick={onDelete}>
    {deleting ? 'Deleting…' : 'Delete use'}
  </button>
{/if}
