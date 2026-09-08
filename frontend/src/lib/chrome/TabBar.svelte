<script lang="ts">
  import { isTabActive, NAV_TABS } from './nav';

  let { pathname }: { pathname: string } = $props();
</script>

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
