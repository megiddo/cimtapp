<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import {
    concentration,
    formatConcentration,
    formatIu,
    formatMg,
    formatMl,
    parseIuInput,
    unusualConcentration,
    waterNeededPerBottle
  } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { type FieldMap } from '$lib/payload';
  import {
    defaultSyringeId,
    fetchCurrentBacBottle,
    fetchPeptideTypes,
    fetchProfiles,
    fetchSyringes,
    mixCompound,
    type BacBottle,
    type PeptideType,
    type Profile,
    type Syringe
  } from '$lib/inventory';
  import ModalSheet from '$lib/ui/ModalSheet.svelte';
  import ProfileMultiSelect from '$lib/ui/ProfileMultiSelect.svelte';
  import VialMixFields from '$lib/ui/VialMixFields.svelte';

  let peptides = $state<PeptideType[]>([]);
  let syringes = $state<Syringe[]>([]);
  let profiles = $state<Profile[]>([]);
  let selectedProfileIds = $state<string[]>([]);
  let bacBottle = $state<BacBottle | null>(null);
  let peptideTypeId = $state('');
  let vialName = $state('');
  let peptideMg = $state('10');
  let bacWaterMl = $state('2');
  let compoundedAt = $state(nowDatetimeLocal());
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);
  let calcOpen = $state(false);

  let calcMg = $state('10');
  let calcTargetMg = $state('2.5');
  let calcTargetIu = $state('25');
  let calcVolumeMl = $state('0.5');
  let calcCapacityIu = $state('50');
  let calcSyringeId = $state('');

  const mg = $derived(parseIuInput(peptideMg));
  const bac = $derived(parseIuInput(bacWaterMl));
  const conc = $derived(mg !== null && bac !== null && mg > 0 && bac > 0 ? concentration(mg, bac) : null);

  const bottleMg = $derived(parseIuInput(calcMg));
  const targetMg = $derived(parseIuInput(calcTargetMg));
  const targetIu = $derived(parseIuInput(calcTargetIu));
  const syringeVolume = $derived(parseIuInput(calcVolumeMl));
  const syringeCapacity = $derived(parseIuInput(calcCapacityIu));
  const waterNeeded = $derived(
    bottleMg !== null &&
      targetMg !== null &&
      targetIu !== null &&
      syringeVolume !== null &&
      syringeCapacity !== null
      ? waterNeededPerBottle(bottleMg, targetMg, targetIu, syringeVolume, syringeCapacity)
      : null
  );
  const mixConc = $derived(
    bottleMg !== null && waterNeeded !== null && waterNeeded > 0
      ? concentration(bottleMg, waterNeeded)
      : null
  );

  onMount(async () => {
    peptides = await fetchPeptideTypes();
    syringes = await fetchSyringes();
    profiles = await fetchProfiles();
    const defaultProfile = profiles.find((item) => item.is_default) ?? profiles[0];
    selectedProfileIds = defaultProfile === undefined ? [] : [defaultProfile.id];
    const requested = page.url.searchParams.get('peptide');
    peptideTypeId =
      requested !== null && peptides.some((peptide) => peptide.id === requested)
        ? requested
        : (peptides[0]?.id ?? '');
    const seededName = peptides.find((peptide) => peptide.id === peptideTypeId);
    if (seededName !== undefined) {
      vialName = seededName.name;
    }
    bacBottle = await fetchCurrentBacBottle();
    const defaultId = defaultSyringeId(syringes, null);
    const seeded = syringes.find((item) => item.id === defaultId) ?? syringes[0];
    if (seeded !== undefined) {
      calcSyringeId = seeded.id;
      calcVolumeMl = String(seeded.volume_ml);
      calcCapacityIu = String(seeded.capacity_iu);
    }
    loaded = true;
  });

  function onPeptideChange() {
    const selected = peptides.find((peptide) => peptide.id === peptideTypeId);
    if (selected === undefined) {
      return;
    }
    const matchesPeptide = peptides.some((peptide) => peptide.name === vialName);
    if (vialName.trim() === '' || matchesPeptide) {
      vialName = selected.name;
    }
  }

  function openCalc() {
    calcMg = peptideMg;
    calcOpen = true;
  }

  function closeCalc() {
    calcOpen = false;
  }

  function onCalcKey(event: KeyboardEvent) {
    if (calcOpen && event.key === 'Escape') {
      closeCalc();
    }
  }

  function onPickSyringe() {
    const selected = syringes.find((item) => item.id === calcSyringeId);
    if (selected === undefined) {
      return;
    }
    calcVolumeMl = String(selected.volume_ml);
    calcCapacityIu = String(selected.capacity_iu);
  }

  function toggleProfile(id: string) {
    if (selectedProfileIds.includes(id)) {
      selectedProfileIds = selectedProfileIds.filter((item) => item !== id);
      return;
    }
    selectedProfileIds = [...selectedProfileIds, id];
  }

  function applyMix() {
    if (bottleMg === null || waterNeeded === null) {
      return;
    }
    peptideMg = String(bottleMg);
    bacWaterMl = String(waterNeeded);
    if (notes === '' && targetMg !== null && targetIu !== null) {
      notes = `${formatMg(targetMg)} mg at ${formatIu(targetIu)} IU`;
    }
    closeCalc();
  }

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (mg === null || bac === null || peptideTypeId === '' || selectedProfileIds.length === 0) {
      return;
    }
    if (conc !== null && unusualConcentration(conc)) {
      const ok = window.confirm(
        `${formatConcentration(conc)} is outside 0.5–20 mg/mL. Add this mix anyway?`
      );
      if (!ok) {
        return;
      }
    }
    pending = true;
    fields = {};
    const result = await mixCompound({
      peptide_type_id: peptideTypeId,
      name: vialName.trim() === '' ? undefined : vialName.trim(),
      peptide_mg: mg,
      bac_water_ml: bac,
      compounded_at: compoundedAt,
      notes: notes === '' ? null : notes,
      profile_ids: selectedProfileIds
    });
    pending = false;
    if (result.ok) {
      await goto('/inventory');
      return;
    }
    fields = result.fields;
  }
</script>

<svelte:window onkeydown={onCalcKey} />

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <form class="auth-form" onsubmit={onSubmit}>
    <VialMixFields
      {peptides}
      bind:peptideTypeId
      bind:vialName
      bind:peptideMg
      bind:bacWaterMl
      bind:compoundedAt
      bind:notes
      {fields}
      {conc}
      bacHint={bacBottle
        ? `${formatMl(bacBottle.remaining_ml)} mL remaining in current bottle`
        : 'Optional: add a BAC bottle to track remaining water.'}
      onpeptidechange={onPeptideChange}
    />
    <a class="chip" href="/inventory/peptides/new">Add peptide</a>
    <button type="button" class="chip" onclick={openCalc}>Calculate water needed</button>
    {#if !bacBottle}
      <a class="chip" href="/inventory/water/new">Add BAC bottle</a>
    {/if}

    <ProfileMultiSelect {profiles} selectedIds={selectedProfileIds} {fields} ontoggle={toggleProfile} />

    <div class="sticky-cta">
      <button type="submit" disabled={pending || mg === null || bac === null || selectedProfileIds.length === 0}>{pending ? 'Adding…' : 'Add to Inventory'}</button>
    </div>
  </form>
{/if}

{#if calcOpen}
  <ModalSheet title="Peptide calculator" titleId="pepcalc-title" extraClass="calculator" onclose={closeCalc}>
      <p class="muted">Calculate the exact amount of bacteriostatic water needed.</p>

      <form class="auth-form" onsubmit={(event) => { event.preventDefault(); applyMix(); }}>
        <label>
          mg per bottle
          <input inputmode="decimal" bind:value={calcMg} />
        </label>

        <div class="calc-row">
          <label>
            Target dosage (mg)
            <input inputmode="decimal" bind:value={calcTargetMg} />
            <span class="muted">mg per shot</span>
          </label>
          <label>
            Target dosage (IU)
            <input inputmode="decimal" bind:value={calcTargetIu} />
            <span class="muted">IU per shot</span>
          </label>
        </div>

        {#if syringes.length > 0}
          <label>
            Syringe
            <select bind:value={calcSyringeId} onchange={onPickSyringe}>
              {#each syringes as syringe (syringe.id)}
                <option value={syringe.id}>{syringe.label}</option>
              {/each}
            </select>
          </label>
        {/if}

        <div class="calc-row">
          <label>
            mL per syringe
            <input inputmode="decimal" bind:value={calcVolumeMl} />
          </label>
          <label>
            IU per syringe
            <input inputmode="decimal" bind:value={calcCapacityIu} />
          </label>
        </div>

        {#if waterNeeded !== null && mixConc !== null && targetMg !== null && targetIu !== null}
          <div class="result-card">
            <div class="result-title">Water needed per bottle</div>
            <div class="result-value">{formatMl(waterNeeded)} <span class="result-unit">mL</span></div>
            <p class="muted">{formatConcentration(mixConc)} · {formatMg(targetMg)} mg at {formatIu(targetIu)} IU</p>
            {#if unusualConcentration(mixConc)}
              <p class="tone warning">{formatConcentration(mixConc)} is outside 0.5–20 mg/mL.</p>
            {/if}
          </div>
          <button type="submit">Use this mix</button>
        {:else}
          <p class="muted">Enter bottle milligrams, a target dose, and the syringe to see water needed.</p>
        {/if}
        <button type="button" class="secondary" onclick={closeCalc}>Cancel</button>
      </form>
  </ModalSheet>
{/if}
