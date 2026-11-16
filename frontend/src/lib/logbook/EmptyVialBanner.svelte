<script lang="ts">
  type Props = {
    onrecompound: () => void;
    onrecalculate: () => void;
  };

  let { onrecompound, onrecalculate }: Props = $props();
</script>

<div class="tone warning banner">
  <p>This vial is probably empty.</p>
  <div class="chip-row">
    <button type="button" class="chip" onclick={onrecompound}>Recompound</button>
    <button type="button" class="chip" onclick={onrecalculate}>Recalculate</button>
  </div>
</div>

<style>
  .banner {
    margin: 0.75rem 0;
    padding: 0.75rem;
    border-radius: 0.5rem;
  }
</style>