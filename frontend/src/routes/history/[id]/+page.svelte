<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import {
    concentrationFromUse,
    formatDoseLine,
    formatMg,
    parseIuInput,
    peptideMgAtConcentration,
    stepIu
  } from '$lib/dose';
  import { toDatetimeLocalValue } from '$lib/datetime';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { fetchSyringes, fetchUse, patchUse, type LoggedUse, type Syringe } from '$lib/inventory';

  let original = $state<LoggedUse | null>(null);
  let syringes = $state<Syringe[]>([]);
  let iuText = $state('');
  let syringeId = $state('');
  let usedAt = $state('');
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);

  const iu = $derived(parseIuInput(iuText));
  const syringe = $derived(syringes.find((item) => item.id === syringeId) ?? null);
  const comparedMg = $derived.by(() => {
    if (original === null || syringe === null || iu === null) {
      return null;
    }
    const conc = concentrationFromUse(original.peptide_mg, original.volume_ml);
    if (conc === null) {
      return null;
    }
    return peptideMgAtConcentration(iu, conc, syringe.volume_ml, syringe.capacity_iu);
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
      syringeId = original.syringe_id ?? syringes[0]?.id ?? '';
      notes = original.notes ?? '';
      const parsed = new Date(original.used_at);
      usedAt = Number.isNaN(parsed.getTime()) ? original.used_at : toDatetimeLocalValue(parsed);
    }
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (original === null || iu === null) {
      return;
    }
    pending = true;
    fields = {};
    const result = await patchUse(original.id, {
      iu,
      syringe_id: syringeId || undefined,
      used_at: usedAt,
      notes: notes === '' ? null : notes
    });
    pending = false;
    if (result.ok) {
      await goto('/history');
      return;
    }
    fields = result.fields;
  }
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if original === null}
  <p>Use not found.</p>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    <label>
      IU
      <div class="stepper">
        <button type="button" aria-label="Decrease IU" onclick={() => (iuText = String(stepIu(iu ?? 1, -1)))}>−</button>
        <input inputmode="decimal" name="iu" bind:value={iuText} />
        <button type="button" aria-label="Increase IU" onclick={() => (iuText = String(stepIu(iu ?? 0, 1)))}>+</button>
      </div>
      {#if firstFieldError(fields, 'iu')}
        <span class="field-error">{firstFieldError(fields, 'iu')}</span>
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

    <div class="sticky-cta">
      <button type="submit" disabled={pending || iu === null}>{pending ? 'Saving…' : 'Save'}</button>
    </div>
  </form>
{/if}
