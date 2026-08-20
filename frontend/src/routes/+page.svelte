<script lang="ts">
  import { onMount } from 'svelte';
  import { formatConcentration, formatDoseLine, formatMg, formatMl, formatIu } from '$lib/dose';
  import { formatTime } from '$lib/history';
  import { remainderTone, remainderToneMessage } from '$lib/remainder';
  import { fetchCurrentCompound, fetchUses, type Compound, type LoggedUse } from '$lib/inventory';

  let current = $state<Compound | null>(null);
  let uses = $state<LoggedUse[]>([]);
  let loaded = $state(false);

  onMount(async () => {
    current = await fetchCurrentCompound();
    uses = await fetchUses({ limit: 5 });
    loaded = true;
  });

  const tone = $derived(current ? remainderTone(current.remaining_mg, current.peptide_mg) : 'default');
  const toneMessage = $derived(remainderToneMessage(tone));
  const last = $derived(uses[0] ?? null);
</script>

{#if !loaded}
  <p class="muted">Loading…</p>
{:else if !current}
  <div class="empty-state">
    <p>Mix a vial to start tracking remainder.</p>
    <a class="chip" href="/inventory/new">Mix vial</a>
  </div>
{:else}
  <section class="hero {tone}">
    <div class="peptide">{current.peptide_type_name}</div>
    <div class="mg">{formatMg(current.remaining_mg)} mg</div>
    <div class="meta">
      {formatMl(current.remaining_ml)} mL · {formatIu(current.remaining_iu)} IU
    </div>
    <div class="meta">{formatConcentration(current.concentration)}</div>
    <div class="meta">Mixed {current.compounded_at.replace('T', ' ')}</div>
    {#if toneMessage}
      <p class="tone {tone}">{toneMessage}</p>
    {/if}
  </section>

  {#if tone === 'danger'}
    <a class="chip" href="/inventory/new">Mix vial</a>
  {:else if last}
    <a class="chip" href="/use/new?iu={last.iu}">Log {formatIu(last.iu)} IU again</a>
  {/if}

  {#if uses.length === 0}
    <p>No uses yet. Log a use from the Log tab.</p>
  {:else}
    <div class="row-list">
      {#each uses as use (use.id)}
        <a class="row" href="/history/{use.id}">
          <span>
            <span class="primary">{formatDoseLine(use.iu, use.peptide_mg)}</span>
            <div class="secondary">{formatTime(use.used_at)} · {use.peptide_type_name}</div>
          </span>
        </a>
      {/each}
    </div>
  {/if}
{/if}
