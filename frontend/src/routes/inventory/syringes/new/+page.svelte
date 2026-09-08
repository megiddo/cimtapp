<script lang="ts">
  import { goto } from '$app/navigation';
  import { parseIuInput, syringeLabel } from '$lib/dose';
  import { type FieldMap } from '$lib/payload';
  import { createSyringe, parseCountInput } from '$lib/inventory';
  import SyringeTypeFields from '$lib/ui/SyringeTypeFields.svelte';

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
    volume !== null && capacity !== null && volume > 0 && capacity > 0 ? syringeLabel(volume, capacity) : ''
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
  <SyringeTypeFields
    bind:volumeMl
    bind:capacityIu
    bind:quantity
    {fields}
    {previewLabel}
    {message}
    showQuantity={true}
  />
  <div class="sticky-cta">
    <button type="submit" disabled={pending || volume === null || capacity === null || count === null}>
      {pending ? 'Adding…' : 'Add syringe type'}
    </button>
  </div>
</form>
