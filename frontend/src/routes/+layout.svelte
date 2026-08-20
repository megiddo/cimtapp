<script lang="ts">
  import { page } from '$app/state';
  import '../app.css';
  import {
    NAV_TABS,
    isTabActive,
    showsSettingsLink,
    showsTabBar,
    titleForPath
  } from '$lib/chrome';

  let { children } = $props();

  const pathname = $derived(page.url.pathname);
  const tabsVisible = $derived(showsTabBar(pathname));
  const title = $derived(titleForPath(pathname));
  const settingsVisible = $derived(showsSettingsLink(pathname));
</script>

<div class="app-shell" class:with-tabs={tabsVisible}>
  <header class="top-bar">
    <h1>{title}</h1>
    {#if settingsVisible}
      <a class="gear" href="/settings" aria-label="Settings">Settings</a>
    {/if}
  </header>

  <main>
    {@render children()}
  </main>

  {#if tabsVisible}
    <nav class="tab-bar" aria-label="Primary">
      {#each NAV_TABS as tab (tab.id)}
        <a
          href={tab.href}
          class:emphasized={tab.emphasized}
          class:active={isTabActive(pathname, tab.href)}
          aria-current={isTabActive(pathname, tab.href) ? 'page' : undefined}
        >
          {tab.label}
        </a>
      {/each}
    </nav>
  {/if}
</div>
