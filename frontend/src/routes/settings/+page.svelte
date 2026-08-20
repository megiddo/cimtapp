<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { logout, PASSWORD_MIN_LENGTH, setPassword, type Me } from '$lib/auth';
  import { firstFieldError, type FieldMap } from '$lib/payload';

  const me = $derived((page.data.me ?? null) as Me | null);
  let password = $state('');
  let pending = $state(false);
  let message = $state('');
  let fields = $state<FieldMap>({});

  async function onSetPassword(event: SubmitEvent) {
    event.preventDefault();
    pending = true;
    message = '';
    fields = {};
    const result = await setPassword(password);
    pending = false;
    if (result.ok) {
      message = 'Password saved.';
      password = '';
      return;
    }
    fields = result.fields;
    message = result.message;
  }

  async function onLogout() {
    await logout();
    await goto('/login');
  }
</script>

{#if me}
  <p>{me.email}</p>
  <p>{me.has_google ? 'Google linked.' : 'No Google login.'}</p>
{/if}

<form class="auth-form" onsubmit={onSetPassword}>
  <label>
    {me?.has_password ? 'Change password' : 'Set password'}
    <input type="password" name="password" minlength={PASSWORD_MIN_LENGTH} bind:value={password} required />
    {#if firstFieldError(fields, 'password')}
      <span class="field-error">{firstFieldError(fields, 'password')}</span>
    {/if}
  </label>
  {#if message}
    <p class="field-error">{message}</p>
  {/if}
  <button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Save password'}</button>
</form>

<button type="button" class="secondary" onclick={onLogout}>Log out</button>
