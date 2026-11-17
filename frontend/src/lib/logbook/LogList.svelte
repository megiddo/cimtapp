<script lang="ts">
  import { formatDoseLine } from '$lib/dose';
  import type { LogEntry } from './types';

  type Props = {
    rows: LogEntry[];
    editHref: (id: string) => string;
  };

  let { rows, editHref }: Props = $props();

  function when(iso: string): string {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
      return iso;
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  }
</script>

{#if rows.length > 0}
  <div class="row-list">
    {#each rows as row (row.id)}
      <a class="row" href={editHref(row.id)}>
        <span>
          <span class="primary">{when(row.logged_at)}</span>
          <span class="secondary">{formatDoseLine(row.iu, row.dose_mg)}</span>
        </span>
      </a>
    {/each}
  </div>
{/if}