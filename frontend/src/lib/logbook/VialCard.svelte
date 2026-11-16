<script lang="ts">
  import { formatMgLeft, perPersonDrawLine } from './remaining';
  import { probablyEmptyLabel } from './probablyEmpty';
  import type { VialSummary } from './types';

  type Props = {
    vial: VialSummary;
    onreplace: () => void;
  };

  let { vial, onreplace }: Props = $props();

  const personLine = $derived(perPersonDrawLine(vial.people));
  const emptyLabel = $derived(probablyEmptyLabel(vial.probably_empty));
  const lid = $derived(vial.lid_color_name === '' ? 'No lid color' : vial.lid_color_name);
</script>

<article class="card vial-card">
  <div>
    <strong>{vial.display_name}</strong>
    {#if emptyLabel}
      <span class="badge">{emptyLabel}</span>
    {/if}
  </div>
  <div class="muted">{lid}</div>
  <div>{formatMgLeft(vial.mg_left)}</div>
  {#if personLine}
    <div class="muted">{personLine}</div>
  {/if}
  <button type="button" class="chip" onclick={onreplace}>Replace</button>
</article>

<style>
  .vial-card {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
  }
</style>