<script lang="ts">
  import ModalSheet from '$lib/ui/ModalSheet.svelte';
  import {
    concentration,
    formatConcentration,
    formatIu,
    formatMg,
    formatMl,
    parseIuInput,
    unusualConcentration
  } from '$lib/dose';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { fetchPeptides } from '$lib/repositories/peptides';
  import { createVial } from '$lib/repositories/vials';
  import { fetchAccountSyringe } from '$lib/repositories/syringe';
  import { fetchPeople } from '$lib/repositories/people';
  import { runDomainMutation } from '$lib/mutations/runDomainMutation';
  import { previewVialName } from './vialName';
  import { pepcalcBacWater } from './pepcalcApply';
  import type { VialModalPrefill } from './vialModalPrefill';
  import PersonChecks from './PersonChecks.svelte';
  import LidColorCombo from './LidColorCombo.svelte';
  import type { Peptide, Person } from './types';

  type Props = {
    open: boolean;
    seed: VialModalPrefill | null;
    knownLidColors: string[];
    onclose: () => void;
    onsaved: () => void;
  };

  let { open, seed, knownLidColors, onclose, onsaved }: Props = $props();

  let people = $state<Person[]>([]);
  let peptides = $state<Peptide[]>([]);
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let pepcalcOpen = $state(false);

  let peptideId = $state('');
  let personIds = $state<string[]>([]);
  let peptideMg = $state('10');
  let bacMl = $state('2');
  let lidColor = $state('');

  let calcMg = $state('10');
  let calcTargetMg = $state('2.5');
  let calcTargetIu = $state('25');
  let calcVolumeMl = $state('0.5');
  let calcCapacityIu = $state('50');

  const mg = $derived(parseIuInput(peptideMg));
  const bac = $derived(parseIuInput(bacMl));
  const selectedPeptide = $derived(peptides.find((item) => item.id === peptideId));
  const selectedNames = $derived(
    personIds
      .map((id) => people.find((person) => person.id === id)?.name)
      .filter((name): name is string => name !== undefined)
  );
  const previewName = $derived(
    selectedPeptide === undefined ? '—' : previewVialName(selectedPeptide.name, selectedNames, mg, bac)
  );

  const bottleMg = $derived(parseIuInput(calcMg));
  const targetMg = $derived(parseIuInput(calcTargetMg));
  const targetIu = $derived(parseIuInput(calcTargetIu));
  const syringeVolume = $derived(parseIuInput(calcVolumeMl));
  const syringeCapacity = $derived(parseIuInput(calcCapacityIu));
  const waterNeeded = $derived(
    bottleMg !== null && targetMg !== null && targetIu !== null && syringeVolume !== null && syringeCapacity !== null
      ? pepcalcBacWater(bottleMg, targetMg, targetIu, syringeVolume, syringeCapacity)
      : null
  );
  const mixConc = $derived(
    bottleMg !== null && waterNeeded !== null && waterNeeded > 0 ? concentration(bottleMg, waterNeeded) : null
  );

  async function loadCatalog() {
    people = await fetchPeople();
    peptides = await fetchPeptides();
    const syringe = await fetchAccountSyringe();
    calcVolumeMl = String(syringe.volume_ml);
    calcCapacityIu = String(syringe.capacity_iu);
  }

  $effect(() => {
    if (!open || seed === null) {
      return;
    }
    void (async () => {
      await loadCatalog();
      peptideId = seed.peptide_id;
      personIds = [...seed.person_ids];
      peptideMg = seed.peptide_mg;
      bacMl = seed.bac_water_ml;
      lidColor = seed.lid_color_name;
      calcMg = seed.pepcalcMg;
      calcTargetMg = seed.pepcalcTargetMg;
      calcTargetIu = seed.pepcalcTargetIu;
      pepcalcOpen = seed.openPepcalc;
      fields = {};
    })();
  });

  function openPepcalc() {
    calcMg = peptideMg;
    pepcalcOpen = true;
  }

  function applyPepcalc(event: SubmitEvent) {
    event.preventDefault();
    if (waterNeeded === null || !(waterNeeded > 0)) {
      return;
    }
    peptideMg = calcMg;
    bacMl = String(waterNeeded);
    pepcalcOpen = false;
  }

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (mg === null || bac === null || personIds.length === 0 || lidColor.trim() === '') {
      return;
    }
    pending = true;
    const outcome = await runDomainMutation(() =>
      createVial({
        peptide_id: peptideId,
        person_ids: personIds,
        peptide_mg: mg,
        bac_water_ml: bac,
        lid_color_name: lidColor.trim()
      })
    );
    pending = false;
    if (outcome.kind === 'ok') {
      onsaved();
      onclose();
      return;
    }
    if (outcome.kind === 'offline') {
      return;
    }
    fields = outcome.fields;
  }
</script>

{#if open}
  <ModalSheet
    title={pepcalcOpen ? 'Peptide calculator' : 'New vial'}
    titleId="vial-modal-title"
    extraClass="calculator"
    onclose={onclose}
  >
    {#if pepcalcOpen}
      <form class="auth-form" onsubmit={applyPepcalc}>
        <label>
          mg per vial
          <input inputmode="decimal" bind:value={calcMg} />
        </label>
        <label>
          Target dosage (mg)
          <input inputmode="decimal" bind:value={calcTargetMg} />
        </label>
        <label>
          Target dosage (IU)
          <input inputmode="decimal" bind:value={calcTargetIu} />
        </label>
        {#if waterNeeded !== null && mixConc !== null && targetMg !== null && targetIu !== null}
          <div class="result-card">
            <div class="result-value">{formatMl(waterNeeded)}</div>
            <p class="muted">{formatConcentration(mixConc)} · {formatMg(targetMg)} mg at {formatIu(targetIu)} IU</p>
            {#if unusualConcentration(mixConc)}
              <p class="tone warning">{formatConcentration(mixConc)} is outside 0.5–20 mg/mL.</p>
            {/if}
          </div>
          <button type="submit">Use this mix</button>
        {/if}
        <button type="button" class="secondary" onclick={() => (pepcalcOpen = false)}>Back</button>
      </form>
    {:else}
      <form class="auth-form" onsubmit={onSubmit}>
        <label>
          Peptide
          <select bind:value={peptideId} disabled={pending}>
            {#each peptides as peptide (peptide.id)}
              <option value={peptide.id}>{peptide.name}</option>
            {/each}
          </select>
        </label>
        <PersonChecks {people} selectedIds={personIds} {pending} onchange={(ids) => (personIds = ids)} />
        <label>
          Peptide mg
          <input inputmode="decimal" bind:value={peptideMg} disabled={pending} />
        </label>
        <label>
          BAC mL
          <input inputmode="decimal" bind:value={bacMl} disabled={pending} />
        </label>
        <LidColorCombo value={lidColor} options={knownLidColors} {pending} oninput={(v) => (lidColor = v)} />
        <p class="muted">Name preview: {previewName}</p>
        <button type="button" class="chip" onclick={openPepcalc}>Calculate</button>
        <button type="submit" disabled={pending || personIds.length === 0}>{pending ? 'Saving…' : 'Save vial'}</button>
        {#if firstFieldError(fields, 'lid_color_name')}
          <span class="field-error">{firstFieldError(fields, 'lid_color_name')}</span>
        {/if}
        <button type="button" class="secondary" onclick={onclose}>Cancel</button>
      </form>
    {/if}
  </ModalSheet>
{/if}