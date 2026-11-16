<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { formatDoseLine, formatMl, parseIuInput, previewDose } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { applyLogDefaults } from '$lib/log/logDefaults';
  import { canSubmitLog } from '$lib/log/canSubmitLog';
  import { formatSourceCaption } from '$lib/log/sourceCaption';
  import { pickInitialPersonId, readRememberedPersonId, rememberPersonId } from '$lib/log/personStorage';
  import {
    buildCreateLogBody,
    createLogEntry,
    deleteLogEntry,
    fetchLogDefaults,
    fetchLogEntries,
    fetchLogEntry,
    patchLogEntry
  } from '$lib/repositories/log';
  import { createPerson, fetchPeople } from '$lib/repositories/people';
  import { fetchCurrentVials } from '$lib/repositories/vials';
  import { runDomainMutation } from '$lib/mutations/runDomainMutation';
  import type { CurrentVial, LogEntry, Person } from './types';
  import PersonChips from './PersonChips.svelte';
  import IuField from './IuField.svelte';
  import LogList from './LogList.svelte';
  import NameModal from './NameModal.svelte';
  import VialModal from './VialModal.svelte';
  import EmptyVialBanner from './EmptyVialBanner.svelte';
  import { prefillNew, prefillRecompound, prefillRecalculate, type VialModalPrefill } from './vialModalPrefill';

  type Props = {
    editId?: string;
  };

  let { editId }: Props = $props();

  let people = $state<Person[]>([]);
  let vials = $state<CurrentVial[]>([]);
  let personId = $state('');
  let vialId = $state('');
  let iuText = $state('');
  let loggedAt = $state(nowDatetimeLocal());
  let isLastDraw = $state(false);
  let sourceLoggedAt = $state<string | null>(null);
  let recent = $state<LogEntry[]>([]);
  let loaded = $state(false);
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let toast = $state('');
  let addOpen = $state(false);
  let addError = $state('');
  let editing = $state(false);
  let syringeVolume = $state(0.5);
  let syringeCapacity = $state(50);
  let probablyEmpty = $state(false);
  let vialModalOpen = $state(false);
  let vialModalSeed = $state<VialModalPrefill | null>(null);

  const iu = $derived(parseIuInput(iuText));
  const selectedVial = $derived(vials.find((item) => item.id === vialId) ?? null);
  const preview = $derived(
    selectedVial && iu !== null
      ? previewDose(
          iu,
          selectedVial.peptide_mg,
          selectedVial.bac_water_ml,
          syringeVolume,
          syringeCapacity
        )
      : null
  );
  const canSave = $derived(canSubmitLog(personId, vialId, iuText) && !pending);
  const sourceCaption = $derived(formatSourceCaption(sourceLoggedAt));
  const lidColors = $derived(
    [...new Set(vials.map((vial) => vial.lid_color_name).filter((name) => name !== ''))]
  );
  const showEmptyBanner = $derived(!editing && (probablyEmpty || selectedVial?.probably_empty === true));

  async function loadPerson(person: string) {
    personId = person;
    rememberPersonId(person);
    fields = {};
    if (editId !== undefined) {
      return;
    }
    const defaults = await fetchLogDefaults(person);
    const seed = applyLogDefaults(defaults, nowDatetimeLocal());
    vialId = seed.vialId;
    iuText = seed.iuText;
    isLastDraw = seed.isLastDraw;
    loggedAt = seed.loggedAt;
    sourceLoggedAt = seed.sourceLoggedAt;
    probablyEmpty = defaults.probably_empty;
    recent = await fetchLogEntries(person, { limit: 20 });
  }

  async function init() {
    people = await fetchPeople();
    vials = await fetchCurrentVials();
  const remembered = readRememberedPersonId();
    if (editId !== undefined) {
      editing = true;
      const row = await fetchLogEntry(editId);
      personId = row.person_id ?? '';
      vialId = row.vial_id ?? '';
      iuText = String(row.iu);
      loggedAt = row.logged_at.length > 16 ? row.logged_at.slice(0, 16) : row.logged_at;
      isLastDraw = row.is_last_draw;
      syringeVolume = row.syringe_volume_ml;
      syringeCapacity = row.syringe_capacity_iu;
      if (personId !== '') {
        recent = await fetchLogEntries(personId, { limit: 20 });
      }
    } else {
      const start = pickInitialPersonId(people, remembered);
      if (start !== '') {
        await loadPerson(start);
      }
    }
    loaded = true;
  }

  onMount(() => {
    void init();
  });

  async function onSelectPerson(id: string) {
    await loadPerson(id);
  }

  async function onAddPerson(name: string) {
    addError = '';
    pending = true;
    const outcome = await createPerson({ name });
    pending = false;
    if (!outcome.ok) {
      addError = firstFieldError(outcome.fields, 'name') ?? outcome.message;
      return;
    }
    addOpen = false;
    people = await fetchPeople();
    vials = await fetchCurrentVials();
    await loadPerson(outcome.data.id);
  }

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (!canSave || iu === null) {
      return;
    }
    pending = true;
    fields = {};
    const body = buildCreateLogBody({
      person_id: personId,
      vial_id: vialId,
      iu,
      logged_at: loggedAt,
      is_last_draw: isLastDraw
    });
    const outcome = await runDomainMutation(() =>
      editing ? patchLogEntry(editId ?? '', body) : createLogEntry(body)
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
  }

  function openMakeVial() {
    vialModalSeed = prefillNew(personId === '' ? [] : [personId]);
    vialModalOpen = true;
  }

  function openRecompound() {
    if (selectedVial === null) {
      return;
    }
    vialModalSeed = prefillRecompound(selectedVial);
    vialModalOpen = true;
  }

  function openRecalculate() {
    if (selectedVial === null) {
      return;
    }
    const latest = recent[0] ?? null;
    vialModalSeed = prefillRecalculate(selectedVial, latest);
    vialModalOpen = true;
  }

  async function onVialSaved() {
    vials = await fetchCurrentVials();
    if (personId !== '') {
      await loadPerson(personId);
    }
  }

  async function onDelete() {
    if (editId === undefined || !confirm('Delete this log entry?')) {
      return;
    }
    pending = true;
    const outcome = await runDomainMutation(() => deleteLogEntry(editId));
    pending = false;
    if (outcome.kind === 'ok') {
      await goto('/');
    }
  }
</script>

{#if toast}
  <p class="toast" role="status">{toast}</p>
{/if}

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if people.length === 0}
  <div class="empty-state">
    <p>Add a person to start logging doses.</p>
    <button type="button" class="chip" onclick={() => (addOpen = true)}>Add person</button>
  </div>
{:else}
  <PersonChips {people} selectedId={personId} {pending} onselect={onSelectPerson} onadd={() => (addOpen = true)} />

  {#if vials.length === 0}
    <div class="empty-state">
      <p>Make a vial for this person before logging.</p>
      <button type="button" class="chip" onclick={openMakeVial}>Make a vial</button>
    </div>
  {:else}
    {#if showEmptyBanner}
      <EmptyVialBanner onrecompound={openRecompound} onrecalculate={openRecalculate} />
    {/if}

    <form class="auth-form has-sticky" onsubmit={onSubmit}>
      <label>
        When
        <input type="datetime-local" bind:value={loggedAt} disabled={pending} />
      </label>

      <label>
        Vial
        <select bind:value={vialId} disabled={pending}>
          {#each vials as vial (vial.id)}
            <option value={vial.id}>{vial.display_name}</option>
          {/each}
        </select>
        {#if selectedVial?.lid_color_name}
          <span class="muted">{selectedVial.lid_color_name}</span>
        {/if}
        {#if firstFieldError(fields, 'vial_id')}
          <span class="field-error">{firstFieldError(fields, 'vial_id')}</span>
        {/if}
      </label>

      <IuField
        value={iuText}
        disabled={pending}
        error={firstFieldError(fields, 'iu') ?? ''}
        oninput={(value) => (iuText = value)}
      />

      {#if preview}
        <p class="preview">{formatDoseLine(iu ?? 0, preview.peptideMg)} · {formatMl(preview.volumeMl)} mL</p>
      {/if}

      <label class="check">
        <input type="checkbox" bind:checked={isLastDraw} disabled={pending} />
        Last draw
      </label>

      {#if sourceCaption}
        <p class="muted">{sourceCaption}</p>
      {/if}

      <div class="sticky-cta">
        <button type="submit" disabled={!canSave}>{pending ? 'Saving…' : 'Save'}</button>
      </div>
    </form>

    {#if editing}
      <button type="button" class="secondary danger" disabled={pending} onclick={onDelete}>Delete</button>
    {/if}

    {#if !editing}
      <LogList rows={recent} editHref={(id) => `/log/${id}`} />
    {/if}
  {/if}
{/if}

<NameModal
  title="Add person"
  open={addOpen}
  {pending}
  error={addError}
  onclose={() => (addOpen = false)}
  onsave={onAddPerson}
/>

<VialModal
  open={vialModalOpen}
  seed={vialModalSeed}
  knownLidColors={lidColors}
  onclose={() => (vialModalOpen = false)}
  onsaved={onVialSaved}
/>