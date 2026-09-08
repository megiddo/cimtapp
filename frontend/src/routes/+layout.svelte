<script lang="ts">
  import { page } from '$app/state';
  import '../app.css';
  import TabBar from '$lib/chrome/TabBar.svelte';
  import TopBar from '$lib/chrome/TopBar.svelte';
  import {
    backHrefForPath,
    needsStickyCta,
    showsSettingsLink,
    showsTabBar,
    titleForPath
  } from '$lib/chrome';

  let { children } = $props();

  const pathname = $derived(page.url.pathname);
  const tabsVisible = $derived(showsTabBar(pathname));
  const title = $derived(titleForPath(pathname));
  const settingsVisible = $derived(showsSettingsLink(pathname));
  const backHref = $derived(backHrefForPath(pathname));
  const sticky = $derived(needsStickyCta(pathname));
</script>

<div class="app-shell" class:with-tabs={tabsVisible}>
  <TopBar {title} {backHref} {settingsVisible} />

  <main class:has-sticky={sticky}>
    {@render children()}
  </main>

  {#if tabsVisible}
    <TabBar {pathname} />
  {/if}
</div>
