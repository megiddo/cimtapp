<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { onMount } from 'svelte';
  import { logout, PASSWORD_MIN_LENGTH, downloadUserSqlite, setPassword, type Me } from '$lib/auth';
  import { APP_VERSION } from '$lib/version';
  import { parseIuInput, syringeLabel } from '$lib/dose';
  import {
    createSyringe,
    fetchSyringes,
    patchSyringe,
    type Syringe
  } from '$lib/inventory';
  import { firstFieldError, type FieldMap } from '$lib/payload';

  const me = $derived((page.data.me ?? null) as Me | null);
  let password = $state('');
  let pendingPassword = $state(false);
  let passwordMessage = $state('');
  let passwordFields = $state<FieldMap>({});

  let syringes = $state<Syringe[]>([]);
  let volumeMl = $state('0.5');
  let capacityIu = $state('50');
  let pendingSyringe = $state(false);
  let syringeFields = $state<FieldMap>({});
  let syringeMessage = $state('');
  let loaded = $state(false);
  let pendingExport = $state(false);
  let exportMessage = $state('');

  const volume = $derived(parseIuInput(volumeMl));
  const capacity = $derived(parseIuInput(capacityIu));
  const previewLabel = $derived(
    volume !== null && capacity !== null && volume > 0 && capacity > 0
      ? syringeLabel(volume, capacity)
      : ''
  );

  onMount(async () => {
    syringes = await fetchSyringes();
    loaded = true;
  });

  async function onSetPassword(event: SubmitEvent) {
    event.preventDefault();
    pendingPassword = true;
    passwordMessage = '';
    passwordFields = {};
    const result = await setPassword(password);
    pendingPassword = false;
    if (result.ok) {
      passwordMessage = 'Password saved.';
      password = '';
      return;
    }
    passwordFields = result.fields;
    passwordMessage = result.message;
  }

  async function onAddSyringe(event: SubmitEvent) {
    event.preventDefault();
    if (volume === null || capacity === null) {
      return;
    }
    pendingSyringe = true;
    syringeFields = {};
    syringeMessage = '';
    const result = await createSyringe({ volume_ml: volume, capacity_iu: capacity });
    pendingSyringe = false;
    if (result.ok) {
      syringes = await fetchSyringes();
      volumeMl = '0.5';
      capacityIu = '50';
      return;
    }
    syringeFields = result.fields;
    syringeMessage = result.message;
  }

  async function onSetDefault(id: string) {
    pendingSyringe = true;
    syringeMessage = '';
    const result = await patchSyringe(id, { is_default: true });
    pendingSyringe = false;
    if (result.ok) {
      syringes = await fetchSyringes();
      return;
    }
    syringeMessage = result.message;
  }

  async function onLogout() {
    await logout();
    await goto('/login');
  }

  async function onDownloadSqlite() {
    pendingExport = true;
    exportMessage = '';
    const result = await downloadUserSqlite();
    pendingExport = false;
    if (!result.ok) {
      exportMessage = result.message;
    }
  }
</script>

{#if me}
  <p>{me.email}</p>
  <p>{me.has_google ? 'Google linked.' : 'No Google login.'}</p>
{/if}

<section>
  <h2 class="day-heading">Syringes</h2>
  {#if !loaded}
    <p class="muted">Loading…</p>
  {:else}
    <div class="row-list">
      {#each syringes as syringe (syringe.id)}
        <div class="row">
          <span>
            <span class="primary">{syringe.label}</span>
            <div class="secondary">{syringe.volume_ml} mL · {syringe.capacity_iu} IU · {syringe.quantity} on hand</div>
          </span>
          {#if syringe.is_default}
            <span class="default-mark">Default</span>
          {:else}
            <button
              type="button"
              class="text"
              disabled={pendingSyringe}
              onclick={() => onSetDefault(syringe.id)}>Set default</button
            >
          {/if}
        </div>
      {/each}
    </div>
  {/if}

  <form class="auth-form" onsubmit={onAddSyringe}>
    <label>
      Volume mL
      <input inputmode="decimal" name="volume_ml" bind:value={volumeMl} required />
      {#if firstFieldError(syringeFields, 'volume_ml')}
        <span class="field-error">{firstFieldError(syringeFields, 'volume_ml')}</span>
      {/if}
    </label>
    <label>
      Capacity IU
      <input inputmode="decimal" name="capacity_iu" bind:value={capacityIu} required />
      {#if firstFieldError(syringeFields, 'capacity_iu')}
        <span class="field-error">{firstFieldError(syringeFields, 'capacity_iu')}</span>
      {/if}
    </label>
    {#if previewLabel}
      <p class="muted">Label: {previewLabel}</p>
    {/if}
    {#if syringeMessage}
      <p class="field-error">{syringeMessage}</p>
    {/if}
    <button type="submit" disabled={pendingSyringe || volume === null || capacity === null}>
      {pendingSyringe ? 'Adding…' : 'Add syringe'}
    </button>
  </form>
</section>

<form class="auth-form" onsubmit={onSetPassword}>
  <label>
    {me?.has_password ? 'Change password' : 'Set password'}
    <input type="password" name="password" minlength={PASSWORD_MIN_LENGTH} bind:value={password} required />
    {#if firstFieldError(passwordFields, 'password')}
      <span class="field-error">{firstFieldError(passwordFields, 'password')}</span>
    {/if}
  </label>
  {#if passwordMessage}
    <p class="field-error">{passwordMessage}</p>
  {/if}
  <button type="submit" disabled={pendingPassword}>{pendingPassword ? 'Saving…' : 'Save password'}</button>
</form>

<section>
  <h2 class="day-heading">Your data</h2>
  <p class="muted">Download this account’s sqlite database.</p>
  {#if exportMessage}
    <p class="field-error">{exportMessage}</p>
  {/if}
  <button type="button" class="secondary" disabled={pendingExport} onclick={onDownloadSqlite}>
    {pendingExport ? 'Downloading…' : 'Download sqlite'}
  </button>
</section>

<button type="button" class="secondary" onclick={onLogout}>Log out</button>

<p class="muted app-version">{APP_VERSION}</p>
