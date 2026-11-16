<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import { logout, type Me } from '$lib/auth';
  import DataSection from '$lib/settings/DataSection.svelte';
  import PasswordSection from '$lib/settings/PasswordSection.svelte';
  import { APP_VERSION } from '$lib/version';
  import {
    createPerson,
    fetchPeople,
    fetchRemovedPeople,
    patchPerson,
    removePerson,
    restorePerson
  } from '$lib/repositories/people';
  import { createPeptide, fetchPeptides, removePeptide } from '$lib/repositories/peptides';
  import { fetchAccountSyringe, putAccountSyringe } from '$lib/repositories/syringe';
  import { nameFieldError } from '$lib/settings/fieldError';
  import { isValidSyringe } from '$lib/settings/syringeForm';
  import NameModal from '$lib/logbook/NameModal.svelte';
  import type { Person, Peptide } from '$lib/logbook/types';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { runDomainMutation } from '$lib/mutations/runDomainMutation';

  const me = $derived((page.data.me ?? null) as Me | null);

  let people = $state<Person[]>([]);
  let removedPeople = $state<Person[]>([]);
  let peptides = $state<Peptide[]>([]);
  let volumeText = $state('0.5');
  let capacityText = $state('50');
  let loaded = $state(false);
  let fields = $state<FieldMap>({});
  let personModal = $state<'add' | 'rename' | null>(null);
  let renameId = $state('');
  let peptideModal = $state(false);

  async function reload() {
    people = await fetchPeople();
    removedPeople = await fetchRemovedPeople();
    peptides = await fetchPeptides();
    const syringe = await fetchAccountSyringe();
    volumeText = String(syringe.volume_ml);
    capacityText = String(syringe.capacity_iu);
    loaded = true;
  }

  onMount(() => {
    void reload();
  });

  async function onLogout() {
    await logout();
    await goto('/login');
  }

  async function savePerson(name: string) {
    fields = {};
    const outcome =
      personModal === 'rename'
        ? await patchPerson(renameId, { name })
        : await createPerson({ name });
    if (!outcome.ok) {
      fields = outcome.fields;
      return;
    }
    personModal = null;
    await reload();
  }

  async function savePeptide(name: string) {
    fields = {};
    const outcome = await createPeptide({ name });
    if (!outcome.ok) {
      fields = outcome.fields;
      return;
    }
    peptideModal = false;
    await reload();
  }

  async function saveSyringe(event: SubmitEvent) {
    event.preventDefault();
    const volume = Number(volumeText);
    const capacity = Number(capacityText);
    if (!isValidSyringe(volume, capacity)) {
      return;
    }
    const outcome = await runDomainMutation(() => putAccountSyringe({ volume_ml: volume, capacity_iu: capacity }));
    if (outcome.kind === 'ok') {
      fields = {};
    }
  }
</script>

{#if me}
  <section class="settings-block">
    <h2 class="day-heading">Account</h2>
    <p>{me.email}</p>
    <p class="muted">Changing the syringe affects the next log entry only. Past rows keep their snapshots.</p>
    <PasswordSection {me} />
    <DataSection />
    <button type="button" class="secondary" onclick={onLogout}>Log out</button>
    <p class="muted app-version">{APP_VERSION}</p>
  </section>
{/if}

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <section class="settings-block">
    <h2 class="day-heading">People</h2>
    <div class="row-list">
      {#each people as person (person.id)}
        <div class="row">
          <span class="primary">{person.name}</span>
          <span class="row-actions">
            <button type="button" class="text" onclick={() => { renameId = person.id; personModal = 'rename'; }}>Rename</button>
            <button type="button" class="text" onclick={async () => { await removePerson(person.id); await reload(); }}>Remove</button>
          </span>
        </div>
      {/each}
    </div>
    <button type="button" class="chip" onclick={() => (personModal = 'add')}>Add person</button>
    {#if removedPeople.length > 0}
      <h3 class="day-heading">Removed</h3>
      {#each removedPeople as person (person.id)}
        <div class="row">
          <span>{person.name}</span>
          <button type="button" class="chip" onclick={async () => { await restorePerson(person.id); await reload(); }}>Restore</button>
        </div>
      {/each}
    {/if}
  </section>

  <section class="settings-block">
    <h2 class="day-heading">Peptides</h2>
    <div class="row-list">
      {#each peptides as peptide (peptide.id)}
        <div class="row">
          <span>{peptide.name}</span>
          <button type="button" class="text" onclick={async () => { await removePeptide(peptide.id); await reload(); }}>Remove</button>
        </div>
      {/each}
    </div>
    <button type="button" class="chip" onclick={() => (peptideModal = true)}>Add peptide</button>
  </section>

  <section class="settings-block">
    <h2 class="day-heading">Syringe</h2>
    <form class="auth-form" onsubmit={saveSyringe}>
      <label>
        mL per syringe
        <input inputmode="decimal" bind:value={volumeText} />
      </label>
      <label>
        IU per syringe
        <input inputmode="decimal" bind:value={capacityText} />
      </label>
      <button type="submit">Save syringe</button>
    </form>
  </section>
{/if}

<NameModal
  title={personModal === 'rename' ? 'Rename person' : 'Add person'}
  open={personModal !== null}
  error={nameFieldError(fields)}
  onclose={() => (personModal = null)}
  onsave={savePerson}
/>

<NameModal title="Add peptide" open={peptideModal} error={nameFieldError(fields)} onclose={() => (peptideModal = false)} onsave={savePeptide} />