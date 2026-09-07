<script lang="ts">
  import type { Snippet } from 'svelte';

  type Props = {
    title: string;
    titleId: string;
    extraClass?: string;
    onclose: () => void;
    children: Snippet;
  };

  let { title, titleId, extraClass = '', onclose, children }: Props = $props();
</script>

<div class="modal-backdrop">
  <button type="button" class="modal-backdrop-hit" aria-label="Close" onclick={onclose}></button>
  <div class="modal-sheet {extraClass}" role="dialog" aria-modal="true" aria-labelledby={titleId} tabindex="-1">
    <h2 id={titleId} class="day-heading">{title}</h2>
    {@render children()}
  </div>
</div>
