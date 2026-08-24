<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { googleStartUrl, PASSWORD_MIN_LENGTH, submitCredentials } from '$lib/auth';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { APP_VERSION } from '$lib/version';

  let mode = $state<'login' | 'register'>('login');
  let email = $state('');
  let password = $state('');
  let pending = $state(false);
  let formError = $state('');
  let fields = $state<FieldMap>({});

  const googleError = $derived(page.url.searchParams.get('error') === 'google');

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    pending = true;
    formError = '';
    fields = {};
    const result = await submitCredentials(mode, email, password);
    pending = false;
    if (result.ok) {
      await goto('/');
      return;
    }
    fields = result.fields;
    formError = result.message;
  }
</script>

<div class="login-screen">
  {#if googleError}
    <p class="banner" role="alert">Google sign-in did not complete. Try again.</p>
  {/if}

  <form class="auth-form" onsubmit={onSubmit}>
    <label>
      Email
      <input type="email" name="email" autocomplete="username" bind:value={email} required />
      {#if firstFieldError(fields, 'email')}
        <span class="field-error">{firstFieldError(fields, 'email')}</span>
      {/if}
    </label>

    <label>
      Password
      <input
        type="password"
        name="password"
        autocomplete={mode === 'register' ? 'new-password' : 'current-password'}
        minlength={PASSWORD_MIN_LENGTH}
        bind:value={password}
        required
      />
      {#if firstFieldError(fields, 'password')}
        <span class="field-error">{firstFieldError(fields, 'password')}</span>
      {/if}
    </label>

    {#if formError && !firstFieldError(fields, 'email') && !firstFieldError(fields, 'password')}
      <p class="field-error" role="alert">{formError}</p>
    {/if}

    <button type="submit" disabled={pending}>
      {#if pending}
        Working…
      {:else if mode === 'register'}
        Create account
      {:else}
        Sign in
      {/if}
    </button>
  </form>

  <p class="switch">
    {#if mode === 'login'}
      Need an account?
      <button type="button" class="text" onclick={() => (mode = 'register')}>Register</button>
    {:else}
      Already have an account?
      <button type="button" class="text" onclick={() => (mode = 'login')}>Sign in</button>
    {/if}
  </p>

  <a class="google" href={googleStartUrl()}>Continue with Google</a>

  <p class="muted app-version">{APP_VERSION}</p>
</div>
