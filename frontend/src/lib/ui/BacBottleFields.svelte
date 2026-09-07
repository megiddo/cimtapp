<script lang="ts">
  import { firstFieldError, type FieldMap } from '$lib/payload';

  type Props = {
    openedAt: string;
    notes: string;
    fields: FieldMap;
    volumeMl?: string;
    showVolume?: boolean;
  };

  let { openedAt = $bindable(), notes = $bindable(), fields, volumeMl = $bindable('10'), showVolume = true }: Props = $props();
</script>

{#if showVolume}
  <label>
    Bottle size mL
    <input inputmode="decimal" bind:value={volumeMl} />
    {#if firstFieldError(fields, 'volume_ml')}
      <span class="field-error">{firstFieldError(fields, 'volume_ml')}</span>
    {/if}
  </label>
{/if}

<label>
  Opened at
  <input type="datetime-local" bind:value={openedAt} />
</label>

<label>
  Notes
  <input type="text" bind:value={notes} />
</label>
