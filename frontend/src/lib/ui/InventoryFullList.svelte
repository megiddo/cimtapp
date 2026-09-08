<script lang="ts" generics="T extends { id: string }">
  import type { Snippet } from 'svelte';

  type Props = {
    open: T[];
    archived: T[];
    hrefFor: (item: T) => string;
    row: Snippet<[T]>;
  };

  let { open, archived, hrefFor, row }: Props = $props();
</script>

<div class="inventory-full-list">
  <section class="stock-group">
    <h2 class="day-heading">Open</h2>
    {#if open.length === 0}
      <div class="empty-state">
        <p>None open.</p>
      </div>
    {:else}
      <div class="cards">
        {#each open as item (item.id)}
          <a class="card" href={hrefFor(item)}>{@render row(item)}</a>
        {/each}
      </div>
    {/if}
  </section>

  <section class="stock-group">
    <h2 class="day-heading">Archived</h2>
    {#if archived.length === 0}
      <div class="empty-state">
        <p>None archived.</p>
      </div>
    {:else}
      <div class="cards">
        {#each archived as item (item.id)}
          <a class="card" href={hrefFor(item)}>{@render row(item)}</a>
        {/each}
      </div>
    {/if}
  </section>
</div>
