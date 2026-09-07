<script lang="ts">
  import { onMount } from 'svelte';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import {
    PROFILE_MAX,
    createProfile,
    deleteProfile,
    fetchProfiles,
    patchProfile,
    type Profile
  } from '$lib/inventory';

  let profiles = $state<Profile[]>([]);
  let profileDrafts = $state<Record<string, string>>({});
  let newProfileName = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let message = $state('');
  let loaded = $state(false);

  onMount(async () => {
    await reload();
    loaded = true;
  });

  async function reload() {
    profiles = await fetchProfiles();
    profileDrafts = Object.fromEntries(profiles.map((item) => [item.id, item.name]));
  }

  async function onAdd(event: SubmitEvent) {
    event.preventDefault();
    const name = newProfileName.trim();
    if (name === '') {
      return;
    }
    pending = true;
    fields = {};
    message = '';
    const result = await createProfile({ name });
    pending = false;
    if (result.ok) {
      newProfileName = '';
      await reload();
      return;
    }
    fields = result.fields;
    message = result.message;
  }

  async function onRename(id: string) {
    const name = (profileDrafts[id] ?? '').trim();
    if (name === '') {
      return;
    }
    pending = true;
    fields = {};
    message = '';
    const result = await patchProfile(id, { name });
    pending = false;
    if (result.ok) {
      await reload();
      return;
    }
    fields = result.fields;
    message = result.message;
  }

  async function onDelete(id: string) {
    pending = true;
    fields = {};
    message = '';
    const result = await deleteProfile(id);
    pending = false;
    if (result.ok) {
      await reload();
      return;
    }
    fields = result.fields;
    message = result.message;
  }
</script>

<section>
  <h2 class="day-heading">Profiles</h2>
  {#if !loaded}
    <p class="muted">Loading…</p>
  {:else}
    <div class="row-list">
      {#each profiles as profile (profile.id)}
        <div class="row profile-row">
          <label>
            <span class="sr-only">Name</span>
            <input
              type="text"
              maxlength="40"
              value={profileDrafts[profile.id] ?? ''}
              disabled={pending}
              oninput={(event) => {
                profileDrafts = { ...profileDrafts, [profile.id]: event.currentTarget.value };
              }}
              onchange={() => onRename(profile.id)}
            />
          </label>
          {#if profile.is_default}
            <span class="default-mark">Default</span>
          {:else}
            <button type="button" class="text" disabled={pending} onclick={() => onDelete(profile.id)}>Delete</button>
          {/if}
        </div>
      {/each}
    </div>
  {/if}

  <form class="auth-form" onsubmit={onAdd}>
    <label>
      Alias
      <input type="text" maxlength="40" bind:value={newProfileName} disabled={pending || profiles.length >= PROFILE_MAX} />
      {#if firstFieldError(fields, 'name')}
        <span class="field-error">{firstFieldError(fields, 'name')}</span>
      {/if}
    </label>
    {#if message && !firstFieldError(fields, 'name')}
      <p class="field-error">{message}</p>
    {/if}
    <button type="submit" disabled={pending || newProfileName.trim() === '' || profiles.length >= PROFILE_MAX}>
      {pending ? 'Saving…' : profiles.length >= PROFILE_MAX ? 'Profile limit reached' : 'Add profile'}
    </button>
  </form>
</section>
