<script lang="ts">
  import { page } from '$app/state';
  import LogScreen from '$lib/logbook/LogScreen.svelte';

  const editId = $derived(page.params.id ?? '');
</script>

<LogScreen editId={editId} />