<script lang="ts">
  import { onMount } from 'svelte';
  import { parseIuInput, syringeLabel } from '$lib/dose';
  import { createSyringe, fetchSyringes, patchSyringe, type Syringe } from '$lib/inventory';
  import type { FieldMap } from '$lib/payload';
  import SyringeTypeFields from '$lib/ui/SyringeTypeFields.svelte';

  let syringes = $state<Syringe[]>([]);
  let volumeMl = $state('0.5');
  let capacityIu = $state('50');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let message = $state('');
  let loaded = $state(false);

  const volume = $derived(parseIuInput(volumeMl));
  const capacity = $derived(parseIuInput(capacityIu));
  const previewLabel = $derived(
    volume !== null && capacity !== null && volume > 0 && capacity > 0 ? syringeLabel(volume, capacity) : ''
  );

  onMount(async () => {
    syringes = await fetchSyringes();
    loaded = true;
  });

  async function onAdd(event: SubmitEvent) {
    event.preventDefault();
    if (volume === null || capacity === null) {
      return;
    }
    pending = true;
    fields = {};
    message = '';
    const result = await createSyringe({ volume_ml: volume, capacity_iu: capacity });
    pending = false;
    if (result.ok) {
      syringes = await fetchSyringes();
      volumeMl = '0.5';
      capacityIu = '50';
      return;
    }
    fields = result.fields;
    message = result.message;
  }

  async function onSetDefault(id: string) {
    pending = true;
    message = '';
    const result = await patchSyringe(id, { is_default: true });
    pending = false;
    if (result.ok) {
      syringes = await fetchSyringes();
      return;
    }
    message = result.message;
  }
</script>

<section>
  <h2 class="day-heading">Syringes</h2>
  {#if !loaded}
    <p class="muted">Loading…</p>
  {:else}
    <div class="row-list">
      {#each syringes as syringe (syringe.id)}
        <div class="row">
          <span>
            <span class="primary">{syringe.label}</span>
            <div class="secondary">{syringe.volume_ml} mL · {syringe.capacity_iu} IU · {syringe.quantity} on hand</div>
          </span>
          {#if syringe.is_default}
            <span class="default-mark">Default</span>
          {:else}
            <button type="button" class="text" disabled={pending} onclick={() => onSetDefault(syringe.id)}>Set default</button>
          {/if}
        </div>
      {/each}
    </div>
  {/if}

  <form class="auth-form" onsubmit={onAdd}>
    <SyringeTypeFields bind:volumeMl bind:capacityIu {fields} {previewLabel} {message} />
    <button type="submit" disabled={pending || volume === null || capacity === null}>
      {pending ? 'Adding…' : 'Add syringe'}
    </button>
  </form>
</section>
