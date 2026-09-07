<script lang="ts">
  import { goto } from '$app/navigation';
  import { onMount } from 'svelte';
  import { downloadUserSqlite, fetchStoreBackup, restoreStoreBackup } from '$lib/auth';

  let pendingExport = $state(false);
  let exportMessage = $state('');
  let backupAvailable = $state(false);
  let pendingRestore = $state(false);
  let restoreMessage = $state('');

  onMount(async () => {
    backupAvailable = (await fetchStoreBackup()).available;
  });

  async function onDownload() {
    pendingExport = true;
    exportMessage = '';
    const result = await downloadUserSqlite();
    pendingExport = false;
    if (!result.ok) {
      exportMessage = result.message;
    }
  }

  async function onRestore() {
    if (
      !confirm(
        'Restore the copy saved before profiles were added? Changes made in profile mode will be discarded, then PepTrack will migrate again.'
      )
    ) {
      return;
    }
    pendingRestore = true;
    restoreMessage = '';
    const result = await restoreStoreBackup();
    pendingRestore = false;
    if (!result.ok) {
      restoreMessage = result.message;
      return;
    }
    await goto('/');
  }
</script>

<section>
  <h2 class="day-heading">Your data</h2>
  <p class="muted">Download this account’s sqlite database.</p>
  {#if exportMessage}
    <p class="field-error">{exportMessage}</p>
  {/if}
  <button type="button" class="secondary" disabled={pendingExport} onclick={onDownload}>
    {pendingExport ? 'Downloading…' : 'Download sqlite'}
  </button>
  {#if backupAvailable}
    <p class="muted">
      An encrypted copy from before the profiles migration is still on the server. Restore it to undo profile-mode edits
      and run that migration again.
    </p>
    {#if restoreMessage}
      <p class="field-error">{restoreMessage}</p>
    {/if}
    <button type="button" class="secondary" disabled={pendingRestore} onclick={onRestore}>
      {pendingRestore ? 'Restoring…' : 'Restore pre-migration backup'}
    </button>
  {/if}
</section>
