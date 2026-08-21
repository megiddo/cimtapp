<script lang="ts">
  import { page } from '$app/state';
  import '../app.css';
  import {
    NAV_TABS,
    backHrefForPath,
    isTabActive,
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
  <header class="top-bar">
    {#if backHref}
      <a class="back" href={backHref} aria-label="Back">‹</a>
    {/if}
    <h1>{title}</h1>
    {#if settingsVisible}
      <a class="gear" href="/settings" aria-label="Settings">Settings</a>
    {/if}
  </header>

  <main class:has-sticky={sticky}>
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
