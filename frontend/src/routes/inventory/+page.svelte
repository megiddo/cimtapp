<script lang="ts">
  import { onMount } from 'svelte';
  import VialCard from '$lib/logbook/VialCard.svelte';
  import VialModal from '$lib/logbook/VialModal.svelte';
  import { prefillNew, prefillRecompound, type VialModalPrefill } from '$lib/logbook/vialModalPrefill';
  import type { VialSummary } from '$lib/logbook/types';
  import { fetchCurrentVials } from '$lib/repositories/vials';

  let vials = $state<VialSummary[]>([]);
  let loaded = $state(false);
  let modalOpen = $state(false);
  let modalSeed = $state<VialModalPrefill | null>(null);

  const lidColors = $derived(
    [...new Set(vials.map((vial) => vial.lid_color_name).filter((name) => name !== ''))]
  );

  async function reload() {
    vials = await fetchCurrentVials();
    loaded = true;
  }

  onMount(() => {
    void reload();
  });

  function openNew() {
    modalSeed = prefillNew([]);
    modalOpen = true;
  }

  function openReplace(vial: VialSummary) {
    modalSeed = prefillRecompound(vial);
    modalOpen = true;
  }

  async function onSaved() {
    loaded = false;
    await reload();
  }
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else}
  <button type="button" class="chip" onclick={openNew}>New vial</button>

  {#if vials.length === 0}
    <div class="empty-state">
      <p>No current vials.</p>
    </div>
  {:else}
    {#each vials as vial (vial.id)}
      <VialCard {vial} onreplace={() => openReplace(vial)} />
    {/each}
  {/if}
{/if}

<VialModal
  open={modalOpen}
  seed={modalSeed}
  knownLidColors={lidColors}
  onclose={() => (modalOpen = false)}
  onsaved={onSaved}
/>