<script lang="ts">
  let { title, backHref, settingsVisible }: { title: string; backHref: string | null; settingsVisible: boolean } =
    $props();
</script>

<header class="top-bar">
  {#if backHref}
    <a class="back" href={backHref} aria-label="Back">‹</a>
  {/if}
  <h1>{title}</h1>
  {#if settingsVisible}
    <a class="gear" href="/settings" aria-label="Settings">Settings</a>
  {/if}
</header>
