<script lang="ts">
  import { PASSWORD_MIN_LENGTH, setPassword, type Me } from '$lib/auth';
  import { firstFieldError, type FieldMap } from '$lib/payload';

  type Props = {
    me: Me | null;
  };

  let { me }: Props = $props();
  let password = $state('');
  let pending = $state(false);
  let message = $state('');
  let fields = $state<FieldMap>({});

  async function onSubmit(event: SubmitEvent) {
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
</script>

<form class="auth-form" onsubmit={onSubmit}>
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
