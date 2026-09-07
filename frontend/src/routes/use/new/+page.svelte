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
  import { OFFLINE_SAVE_MESSAGE, saveWhileOnline } from '$lib/offline';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    defaultOpenVialId,
    defaultSyringeId,
    fetchOpenCompounds,
    fetchProfiles,
    fetchSyringes,
    fetchUses,
    logUse,
    vialLabel,
    vialsForProfile,
    type Compound,
    type Profile,
    type Syringe
  } from '$lib/inventory';

  let profiles = $state<Profile[]>([]);
  let profileId = $state('');
  let step = $state<'pick' | 'dose'>('pick');
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

  onMount(async () => {
    profiles = await fetchProfiles();
    openVials = await fetchOpenCompounds();
    syringes = await fetchSyringes();
    const requestedProfile = page.url.searchParams.get('profile_id');
    if (profiles.length === 1) {
      await selectProfile(profiles[0].id);
    } else if (requestedProfile !== null && profiles.some((item) => item.id === requestedProfile)) {
      await selectProfile(requestedProfile);
    }
    loaded = true;
  });

  async function selectProfile(id: string) {
    profileId = id;
    vials = vialsForProfile(openVials, id);
    const requestedIu = page.url.searchParams.get('iu');
    const recent = await fetchUses({ profile_id: id, limit: 1 });
    const last = recent[0];
    compoundId = defaultOpenVialId(
      vials,
      page.url.searchParams.get('compound_id') ?? last?.compound_id ?? null
    );
    syringeId = defaultSyringeId(syringes, last?.syringe_id ?? null);
    if (requestedIu !== null && requestedIu !== '') {
      iuText = requestedIu;
    } else if (last !== undefined) {
      iuText = String(last.iu);
    } else {
      iuText = '25';
    }
    step = 'dose';
  }

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (selected === null || iu === null || profileId === '') {
      return;
    }
    pending = true;
    fields = {};
    toast = '';
    iuError = '';
    try {
      const result = await saveWhileOnline(() =>
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
      if (result.ok) {
        await goto('/');
        return;
      }
      fields = result.fields;
      iuError = firstFieldError(result.fields, 'iu') ?? result.message;
    } catch {
      pending = false;
      toast = OFFLINE_SAVE_MESSAGE;
    }
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
      <button type="submit" disabled={pending || !loaded || iu === null || selected === null}>{pending ? 'Saving…' : 'Save'}</button>
    </div>
  </form>
{/if}
