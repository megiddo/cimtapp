<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { logout, type Me } from '$lib/auth';
  import DataSection from '$lib/settings/DataSection.svelte';
  import PasswordSection from '$lib/settings/PasswordSection.svelte';
  import ProfilesSection from '$lib/settings/ProfilesSection.svelte';
  import SyringesSection from '$lib/settings/SyringesSection.svelte';
  import { APP_VERSION } from '$lib/version';

  const me = $derived((page.data.me ?? null) as Me | null);

  async function onLogout() {
    await logout();
    await goto('/login');
  }
</script>

{#if me}
  <p>{me.email}</p>
  <p>{me.has_google ? 'Google linked.' : 'No Google login.'}</p>
{/if}

<ProfilesSection />
<SyringesSection />
<PasswordSection {me} />
<DataSection />

<button type="button" class="secondary" onclick={onLogout}>Log out</button>

<p class="muted app-version">{APP_VERSION}</p>
