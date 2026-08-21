<script lang="ts">
  import { goto } from '$app/navigation';
  import { parseIuInput, syringeLabel } from '$lib/dose';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { createSyringe, parseCountInput } from '$lib/inventory';

  let volumeMl = $state('1');
  let capacityIu = $state('100');
  let quantity = $state('10');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let message = $state('');

  const volume = $derived(parseIuInput(volumeMl));
  const capacity = $derived(parseIuInput(capacityIu));
  const count = $derived(parseCountInput(quantity));
  const previewLabel = $derived(
    volume !== null && capacity !== null && volume > 0 && capacity > 0
      ? syringeLabel(volume, capacity)
      : ''
  );

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (volume === null || capacity === null || count === null) {
      return;
    }
    pending = true;
    fields = {};
    message = '';
    const result = await createSyringe({
      volume_ml: volume,
      capacity_iu: capacity,
      quantity: count
    });
    pending = false;
    if (result.ok) {
      await goto('/inventory');
      return;
    }
    fields = result.fields;
    message = result.message;
  }
</script>

<form class="auth-form" onsubmit={onSubmit}>
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
  <label>
    How many
    <input inputmode="numeric" name="quantity" bind:value={quantity} required />
    {#if firstFieldError(fields, 'quantity')}
      <span class="field-error">{firstFieldError(fields, 'quantity')}</span>
    {/if}
  </label>
  {#if previewLabel}
    <p class="muted">Label: {previewLabel}</p>
  {/if}
  {#if message && !firstFieldError(fields, 'volume_ml') && !firstFieldError(fields, 'capacity_iu') && !firstFieldError(fields, 'quantity')}
    <p class="field-error">{message}</p>
  {/if}
  <div class="sticky-cta">
    <button type="submit" disabled={pending || volume === null || capacity === null || count === null}>
      {pending ? 'Adding…' : 'Add syringe type'}
    </button>
  </div>
</form>
