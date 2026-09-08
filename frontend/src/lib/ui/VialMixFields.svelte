<script lang="ts">
  import { formatConcentration } from '$lib/dose';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import type { PeptideType } from '$lib/repositories/types';

  type Props = {
    peptides: PeptideType[];
    peptideTypeId: string;
    vialName: string;
    peptideMg: string;
    bacWaterMl: string;
    compoundedAt: string;
    notes: string;
    fields: FieldMap;
    conc: number | null;
    bacHint?: string;
    onpeptidechange?: () => void;
  };

  let {
    peptides,
    peptideTypeId = $bindable(),
    vialName = $bindable(),
    peptideMg = $bindable(),
    bacWaterMl = $bindable(),
    compoundedAt = $bindable(),
    notes = $bindable(),
    fields,
    conc,
    bacHint,
    onpeptidechange
  }: Props = $props();
</script>

<label>
  Peptide
  <select bind:value={peptideTypeId} onchange={onpeptidechange}>
    {#each peptides as peptide (peptide.id)}
      <option value={peptide.id}>{peptide.name}</option>
    {/each}
  </select>
  {#if firstFieldError(fields, 'peptide_type_id')}
    <span class="field-error">{firstFieldError(fields, 'peptide_type_id')}</span>
  {/if}
</label>

<label>
  Vial name
  <input type="text" bind:value={vialName} maxlength="80" />
  {#if firstFieldError(fields, 'name')}
    <span class="field-error">{firstFieldError(fields, 'name')}</span>
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
  {#if bacHint}
    <span class="muted">{bacHint}</span>
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
