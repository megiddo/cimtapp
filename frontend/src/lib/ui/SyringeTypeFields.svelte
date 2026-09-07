<script lang="ts">
  import { firstFieldError, type FieldMap } from '$lib/payload';

  type Props = {
    volumeMl: string;
    capacityIu: string;
    fields: FieldMap;
    previewLabel: string;
    quantity?: string;
    showQuantity?: boolean;
    message?: string;
  };

  let {
    volumeMl = $bindable(),
    capacityIu = $bindable(),
    quantity = $bindable(''),
    fields,
    previewLabel,
    showQuantity = false,
    message = ''
  }: Props = $props();
</script>

<label>
  Volume mL
  <input inputmode="decimal" name="volume_ml" bind:value={volumeMl} required />
  {#if firstFieldError(fields, 'volume_ml')}
    <span class="field-error">{firstFieldError(fields, 'volume_ml')}</span>
  {/if}
</label>
<label>
  Capacity IU
  <input inputmode="decimal" name="capacity_iu" bind:value={capacityIu} required />
  {#if firstFieldError(fields, 'capacity_iu')}
    <span class="field-error">{firstFieldError(fields, 'capacity_iu')}</span>
  {/if}
</label>
{#if showQuantity}
  <label>
    How many
    <input inputmode="numeric" name="quantity" bind:value={quantity} required />
    {#if firstFieldError(fields, 'quantity')}
      <span class="field-error">{firstFieldError(fields, 'quantity')}</span>
    {/if}
  </label>
{/if}
{#if previewLabel}
  <p class="muted">Label: {previewLabel}</p>
{/if}
{#if message && !firstFieldError(fields, 'volume_ml') && !firstFieldError(fields, 'capacity_iu') && !firstFieldError(fields, 'quantity')}
  <p class="field-error">{message}</p>
{/if}
