<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { concentration, formatConcentration, parseIuInput, unusualConcentration } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { fetchPeptideTypes, mixCompound, type PeptideType } from '$lib/inventory';

  let peptides = $state<PeptideType[]>([]);
  let peptideTypeId = $state('');
  let peptideMg = $state('10');
  let bacWaterMl = $state('2');
  let compoundedAt = $state(nowDatetimeLocal());
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);

  const mg = $derived(parseIuInput(peptideMg));
  const bac = $derived(parseIuInput(bacWaterMl));
  const conc = $derived(mg !== null && bac !== null && mg > 0 && bac > 0 ? concentration(mg, bac) : null);

  onMount(async () => {
    peptides = await fetchPeptideTypes();
    peptideTypeId = peptides[0]?.id ?? '';
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (mg === null || bac === null || peptideTypeId === '') {
      return;
    }
    if (conc !== null && unusualConcentration(conc)) {
      const ok = window.confirm(
        `${formatConcentration(conc)} is outside 0.5–20 mg/mL. Mix this vial anyway?`
      );
      if (!ok) {
        return;
      }
    }
    pending = true;
    fields = {};
    const result = await mixCompound({
      peptide_type_id: peptideTypeId,
      peptide_mg: mg,
      bac_water_ml: bac,
      compounded_at: compoundedAt,
      notes: notes === '' ? null : notes
    });
    pending = false;
    if (result.ok) {
      await goto('/inventory');
      return;
    }
    fields = result.fields;
  }
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
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

    <div class="sticky-cta">
      <button type="submit" disabled={pending || mg === null || bac === null}>{pending ? 'Mixing…' : 'Mix'}</button>
    </div>
  </form>
{/if}
