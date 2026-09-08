<script lang="ts">
  import type { Snippet } from 'svelte';

  type Props = {
    title: string;
    fullListHref: string;
    empty: string;
    count: number;
    children?: Snippet;
  };

  let { title, fullListHref, empty, count, children }: Props = $props();
</script>

<section>
  <h2 class="day-heading">{title}</h2>
  {#if count === 0}
    <div class="empty-state">
      <p>{empty}</p>
    </div>
  {:else}
    <div class="cards">
      {@render children?.()}
    </div>
  {/if}
  <a class="full-list-link" href={fullListHref}>Full list</a>
</section>
