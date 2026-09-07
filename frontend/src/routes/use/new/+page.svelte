<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import {
    formatDoseLine,
    formatMl,
    parseIuInput,
    previewDose,
    FALLBACK_SYRINGE_CAPACITY_IU,
    FALLBACK_SYRINGE_VOLUME_ML
  } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    fetchOpenCompounds,
    fetchProfiles,
    fetchSyringes,
    fetchUses,
    logUse,
    vialLabel,
    type Compound,
    type Profile,
    type Syringe
  } from '$lib/inventory';
  import { canSubmitDose, initialLogStep, readLogSeed, seedDoseForProfile, type LogStep } from '$lib/log/logUseVm';
  import { runDomainMutation } from '$lib/mutations/runDomainMutation';

  let profiles = $state<Profile[]>([]);
  let profileId = $state('');
  let step = $state<LogStep>('pick');
  let openVials = $state<Compound[]>([]);
  let vials = $state<Compound[]>([]);
  let compoundId = $state('');
  let syringes = $state<Syringe[]>([]);
  let iuText = $state('25');
  let syringeId = $state('');
  let usedAt = $state(nowDatetimeLocal());
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let loaded = $state(false);
  let toast = $state('');
  let iuError = $state('');

  const iu = $derived(parseIuInput(iuText));
  const selected = $derived(vials.find((item) => item.id === compoundId) ?? null);
  const syringe = $derived(syringes.find((item) => item.id === syringeId) ?? null);
  const selectedProfile = $derived(profiles.find((item) => item.id === profileId) ?? null);
  const preview = $derived(
    selected && iu !== null
      ? previewDose(
          iu,
          selected.peptide_mg,
          selected.bac_water_ml,
          syringe?.volume_ml ?? FALLBACK_SYRINGE_VOLUME_ML,
          syringe?.capacity_iu ?? FALLBACK_SYRINGE_CAPACITY_IU
        )
      : null
  );
  const canSave = $derived(canSubmitDose(selected, iu, profileId));

  onMount(async () => {
    profiles = await fetchProfiles();
    openVials = await fetchOpenCompounds();
    syringes = await fetchSyringes();
    const seed = readLogSeed(page.url.searchParams);
    const start = initialLogStep(profiles, seed.requestedProfileId);
    if (start.step === 'dose' && start.profileId !== '') {
      await selectProfile(start.profileId);
    }
    loaded = true;
  });

  async function selectProfile(id: string) {
    const seed = readLogSeed(page.url.searchParams);
    const recent = await fetchUses({ profile_id: id, limit: 1 });
    const seeded = seedDoseForProfile({
      profileId: id,
      openVials,
      syringes,
      lastUse: recent[0],
      requestedCompoundId: seed.requestedCompoundId,
      requestedIu: seed.requestedIu
    });
    profileId = seeded.profileId;
    vials = seeded.vials;
    compoundId = seeded.compoundId;
    syringeId = seeded.syringeId;
    iuText = seeded.iuText;
    step = 'dose';
  }

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (!canSave || selected === null || iu === null) {
      return;
    }
    pending = true;
    fields = {};
    toast = '';
    iuError = '';
    const outcome = await runDomainMutation(() =>
      logUse({
        iu,
        profile_id: profileId,
        compound_id: selected.id,
        syringe_id: syringeId === '' ? null : syringeId,
        used_at: usedAt,
        notes: notes === '' ? null : notes
      })
    );
    pending = false;
    if (outcome.kind === 'ok') {
      await goto('/');
      return;
    }
    if (outcome.kind === 'offline') {
      toast = outcome.message;
      return;
    }
    fields = outcome.fields;
    iuError = firstFieldError(outcome.fields, 'iu') ?? outcome.message;
  }
</script>

{#if toast}
  <p class="toast" role="status">{toast}</p>
{/if}

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if profiles.length === 0}
  <div class="empty-state">
    <p>Add a profile in Settings before logging a use.</p>
    <a class="chip" href="/settings">Settings</a>
  </div>
{:else if step === 'pick'}
  <div class="row-list">
    {#each profiles as profile (profile.id)}
      <button type="button" class="row" onclick={() => selectProfile(profile.id)}>
        <span>
          <span class="primary">{profile.name}</span>
          {#if profile.is_default}
            <div class="secondary">Default</div>
          {/if}
        </span>
      </button>
    {/each}
  </div>
{:else if vials.length === 0}
  <div class="empty-state">
    {#if selectedProfile}
      <p>No open vials for {selectedProfile.name}.</p>
    {:else}
      <p>Add to inventory before logging a use.</p>
    {/if}
    {#if profiles.length > 1}
      <button type="button" class="chip" onclick={() => (step = 'pick')}>Change profile</button>
    {/if}
    <a class="chip" href="/inventory/new">Add to Inventory</a>
  </div>
{:else}
  <form class="auth-form has-sticky" onsubmit={onSubmit}>
    {#if selectedProfile}
      <p class="muted">
        {selectedProfile.name}
        {#if profiles.length > 1}
          · <button type="button" class="text" onclick={() => (step = 'pick')}>Change</button>
        {/if}
      </p>
    {/if}

    <label>
      Vial
      <select bind:value={compoundId} disabled={pending || vials.length === 1}>
        {#each vials as item (item.id)}
          <option value={item.id}>{vialLabel(item)}</option>
        {/each}
      </select>
      {#if firstFieldError(fields, 'compound_id')}
        <span class="field-error">{firstFieldError(fields, 'compound_id')}</span>
      {/if}
    </label>

    <label>
      IU
      <input inputmode="decimal" name="iu" bind:value={iuText} disabled={pending} />
      {#if iuError}
        <span class="field-error">{iuError}</span>
      {/if}
    </label>

    {#if preview}
      <p class="preview">{formatDoseLine(iu ?? 0, preview.peptideMg)} · {formatMl(preview.volumeMl)} mL</p>
    {/if}

    <label>
      Syringe
      <select bind:value={syringeId}>
        <option value="">None</option>
        {#each syringes as item (item.id)}
          <option value={item.id}>{item.label} · {item.quantity} left</option>
        {/each}
      </select>
      {#if syringe && syringe.quantity < 1}
        <span class="muted">No syringes of this type on hand — logging still works.</span>
      {/if}
      {#if firstFieldError(fields, 'syringe_id')}
        <span class="field-error">{firstFieldError(fields, 'syringe_id')}</span>
      {/if}
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
      <button type="submit" disabled={pending || !loaded || !canSave}>{pending ? 'Saving…' : 'Save'}</button>
    </div>
  </form>
{/if}
