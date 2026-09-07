<script lang="ts">
  import { goto } from '$app/navigation';
  import { parseIuInput } from '$lib/dose';
  import { nowDatetimeLocal } from '$lib/datetime';
  import { type FieldMap } from '$lib/payload';
  import { addBacBottle } from '$lib/inventory';
  import BacBottleFields from '$lib/ui/BacBottleFields.svelte';

  let volumeMl = $state('10');
  let openedAt = $state(nowDatetimeLocal());
  let notes = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});

  const volume = $derived(parseIuInput(volumeMl));

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (volume === null) {
      return;
    }
    pending = true;
    fields = {};
    const result = await addBacBottle({
      volume_ml: volume,
      opened_at: openedAt,
      notes: notes === '' ? null : notes
    });
    pending = false;
    if (result.ok) {
      await goto('/inventory');
      return;
    }
    fields = result.fields;
  }
</script>

<form class="auth-form" onsubmit={onSubmit}>
  <BacBottleFields bind:volumeMl bind:openedAt bind:notes {fields} />

  <div class="sticky-cta">
    <button type="submit" disabled={pending || volume === null || volume <= 0}>
      {pending ? 'Adding…' : 'Add BAC bottle'}
    </button>
  </div>
</form>
