<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import {
    formatDoseLine,
    formatMl,
    parseIuInput,
    previewDose,
    stepIu
  } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    defaultSyringeId,
    fetchCurrentCompound,
    fetchSyringes,
    fetchUses,
    logUse,
    type Compound,
    type Syringe
  } from '$lib/inventory';

  let current = $state<Compound | null>(null);
  let syringes = $state<Syringe[]>([]);
  let iuText = $state('25');
  let syringeId = $state('');
  let usedAt = $state(nowDatetimeLocal());
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);

  const iu = $derived(parseIuInput(iuText));
  const syringe = $derived(syringes.find((item) => item.id === syringeId) ?? syringes[0] ?? null);
  const preview = $derived(
    current && syringe && iu !== null
      ? previewDose(iu, current.peptide_mg, current.bac_water_ml, syringe.volume_ml, syringe.capacity_iu)
      : null
  );

  onMount(async () => {
    const requested = page.url.searchParams.get('iu');
    if (requested !== null && requested !== '') {
      iuText = requested;
    }
    current = await fetchCurrentCompound();
    syringes = await fetchSyringes();
    const recent = await fetchUses({ limit: 1 });
    syringeId = defaultSyringeId(syringes, recent[0]?.syringe_id ?? null);
    loaded = true;
  });

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (current === null || iu === null) {
      return;
    }
    pending = true;
    fields = {};
    const result = await logUse({
      iu,
      syringe_id: syringeId || undefined,
      used_at: usedAt,
      notes: notes === '' ? null : notes
    });
    pending = false;
    if (result.ok) {
      await goto('/');
      return;
    }
    fields = result.fields;
  }
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if current === null}
  <p>Mix a vial before logging a use.</p>
  <a class="chip" href="/inventory/new">Mix vial</a>
{:else}
  <form class="auth-form has-sticky" onsubmit={onSubmit}>
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

    {#if preview}
      <p class="preview">{formatDoseLine(iu ?? 0, preview.peptideMg)} · {formatMl(preview.volumeMl)} mL</p>
    {/if}

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
