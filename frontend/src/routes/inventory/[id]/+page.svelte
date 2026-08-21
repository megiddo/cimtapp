<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import { concentration, formatConcentration, parseIuInput, unusualConcentration } from '$lib/dose';
  import { toDatetimeLocalValue } from '$lib/datetime';
  import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '$lib/offline';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    deleteCompound,
    fetchCompound,
    fetchPeptideTypes,
    patchCompound,
    type Compound,
    type PeptideType
  } from '$lib/inventory';

  let compound = $state<Compound | null>(null);
  let peptides = $state<PeptideType[]>([]);
  let peptideTypeId = $state('');
  let peptideMg = $state('');
  let bacWaterMl = $state('');
  let compoundedAt = $state('');
  let notes = $state('');
  let pending = $state(false);
  let deleting = $state(false);
  let fields = $state<FieldMap>({});
  let formError = $state('');
  let toast = $state('');
  let loaded = $state(false);

  const mg = $derived(parseIuInput(peptideMg));
  const bac = $derived(parseIuInput(bacWaterMl));
  const conc = $derived(mg !== null && bac !== null && mg > 0 && bac > 0 ? concentration(mg, bac) : null);

  onMount(async () => {
    const id = page.params.id;
    peptides = await fetchPeptideTypes();
    if (id === undefined) {
      loaded = true;
      return;
    }
    compound = await fetchCompound(id);
    if (compound !== null) {
      peptideTypeId = compound.peptide_type_id;
      peptideMg = String(compound.peptide_mg);
      bacWaterMl = String(compound.bac_water_ml);
      notes = compound.notes ?? '';
      const parsed = new Date(compound.compounded_at);
      compoundedAt = Number.isNaN(parsed.getTime())
        ? compound.compounded_at
        : toDatetimeLocalValue(parsed);
    }
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    const target = compound;
    if (target === null || mg === null || bac === null || peptideTypeId === '') {
      return;
    }
    if (conc !== null && unusualConcentration(conc)) {
      const ok = window.confirm(
        `${formatConcentration(conc)} is outside 0.5–20 mg/mL. Save this mix anyway?`
      );
      if (!ok) {
        return;
      }
    }
    pending = true;
    fields = {};
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() =>
        patchCompound(target.id, {
          peptide_type_id: peptideTypeId,
          peptide_mg: mg,
          bac_water_ml: bac,
          compounded_at: compoundedAt,
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
    const target = compound;
    if (target === null || target.has_uses) {
      return;
    }
    const ok = window.confirm('Delete this unused mix? This cannot be undone.');
    if (!ok) {
      return;
    }
    deleting = true;
    formError = '';
    toast = '';
    try {
      const result = await saveWhileOnline(() => deleteCompound(target.id));
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
{:else if compound === null}
  <div class="empty-state">
    <p>Vial not found.</p>
    <a class="chip" href="/inventory">Back to vials</a>
  </div>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    <label>
      Peptide
      <select bind:value={peptideTypeId}>
        {#each peptides as peptide (peptide.id)}
          <option value={peptide.id}>{peptide.name}</option>
        {/each}
      </select>
      {#if firstFieldError(fields, 'peptide_type_id')}
        <span class="field-error">{firstFieldError(fields, 'peptide_type_id')}</span>
      {/if}
    </label>
    <a class="chip" href="/inventory/peptides/new?from=/inventory/{compound.id}">Add peptide</a>

    <label>
      Peptide mg
      <input inputmode="decimal" bind:value={peptideMg} />
      {#if firstFieldError(fields, 'peptide_mg')}
        <span class="field-error">{firstFieldError(fields, 'peptide_mg')}</span>
      {/if}
    </label>

    <label>
      BAC water mL
      <input inputmode="decimal" bind:value={bacWaterMl} />
      {#if conc !== null}
        <span class="muted">{formatConcentration(conc)}</span>
      {/if}
      {#if firstFieldError(fields, 'bac_water_ml')}
        <span class="field-error">{firstFieldError(fields, 'bac_water_ml')}</span>
      {/if}
    </label>

    <label>
      Mixed at
      <input type="datetime-local" bind:value={compoundedAt} />
    </label>

    <label>
      Notes
      <input type="text" bind:value={notes} />
    </label>

    {#if formError && !firstFieldError(fields, 'peptide_type_id') && !firstFieldError(fields, 'peptide_mg') && !firstFieldError(fields, 'bac_water_ml')}
      <p class="field-error" role="alert">{formError}</p>
    {/if}

    <div class="sticky-cta">
      <button type="submit" disabled={pending || deleting || mg === null || bac === null}>
        {pending ? 'Saving…' : 'Save'}
      </button>
    </div>
  </form>

  {#if compound.has_uses}
    <p class="muted below-fold">Delete is unavailable after a use is logged.</p>
  {:else}
    <button class="danger" type="button" disabled={pending || deleting} onclick={onDelete}>
      {deleting ? 'Deleting…' : 'Delete from inventory'}
    </button>
  {/if}
{/if}
