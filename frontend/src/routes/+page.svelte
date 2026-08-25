<script lang="ts">
  import { onMount } from 'svelte';
  import { formatConcentration, formatDoseLine, formatMg, formatMl, formatIu } from '$lib/dose';
  import { formatDateTime } from '$lib/history';
  import { remainderTone, remainderToneMessage } from '$lib/remainder';
  import { fetchOpenCompounds, fetchUses, vialLabel, type Compound, type LoggedUse } from '$lib/inventory';

  let open = $state<Compound[]>([]);
  let uses = $state<LoggedUse[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    open = await fetchOpenCompounds();
    uses = await fetchUses({ limit: 5 });
    loaded = true;
  });

  const last = $derived(uses[0] ?? null);
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if open.length === 0}
  <div class="empty-state">
    <p>Add to inventory to start tracking remainder.</p>
    <a class="chip" href="/inventory/new">Add to Inventory</a>
  </div>
{:else}
  {#each open as vial (vial.id)}
    {@const tone = remainderTone(vial.remaining_mg, vial.peptide_mg)}
    {@const toneMessage = remainderToneMessage(tone)}
    <section class="hero {tone}">
      <div class="peptide">{vialLabel(vial)}</div>
      <div class="mg">{formatMg(vial.remaining_mg)} mg</div>
      <div class="meta">
        {formatMl(vial.remaining_ml)} mL · {formatIu(vial.remaining_iu)} IU
      </div>
      <div class="meta">{formatConcentration(vial.concentration)}</div>
      <div class="meta">Mixed {vial.compounded_at.replace('T', ' ')}</div>
      {#if toneMessage}
        <p class="tone {tone}">{toneMessage}</p>
      {/if}
      {#if tone === 'danger'}
        <a class="chip" href="/inventory/new">Add to Inventory</a>
      {:else}
        <a class="chip" href="/use/new?compound_id={vial.id}{last && last.compound_id === vial.id ? `&iu=${last.iu}` : ''}">
          {last && last.compound_id === vial.id ? `Log ${formatIu(last.iu)} IU again` : 'Log use'}
        </a>
      {/if}
    </section>
  {/each}

  {#if uses.length === 0}
    <p>No uses yet. Log a use from the Log tab.</p>
  {:else}
    <div class="row-list">
      {#each uses as use (use.id)}
        <a class="row" href="/history/{use.id}">
          <span>
            <span class="primary">{formatDoseLine(use.iu, use.peptide_mg)}</span>
            <div class="secondary">{formatDateTime(use.used_at)} · {vialLabel({ name: use.compound_name, peptide_type_name: use.peptide_type_name })}</div>
          </span>
        </a>
      {/each}
    </div>
  {/if}
{/if}
