<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import { createPeptideType } from '$lib/inventory';

  let name = $state('');
  let pending = $state(false);
  let fields = $state<FieldMap>({});
  let message = $state('');

  const trimmed = $derived(name.trim());
  const from = $derived(page.url.searchParams.get('from') ?? '');

  async function onSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (trimmed === '') {
      return;
    }
    pending = true;
    fields = {};
    message = '';
    const result = await createPeptideType({ name: trimmed });
    pending = false;
    if (result.ok) {
      if (from.startsWith('/inventory/') && from !== '/inventory/peptides/new') {
        await goto(from);
        return;
      }
      await goto(`/inventory/new?peptide=${encodeURIComponent(result.data.id)}`);
      return;
    }
    fields = result.fields;
    message = result.message;
  }
</script>

<form class="auth-form" onsubmit={onSubmit}>
  <label>
    Peptide name
    <input type="text" name="name" maxlength="80" bind:value={name} required />
    {#if firstFieldError(fields, 'name')}
      <span class="field-error">{firstFieldError(fields, 'name')}</span>
    {/if}
  </label>
  {#if message && !firstFieldError(fields, 'name')}
    <p class="field-error">{message}</p>
  {/if}
  <div class="sticky-cta">
    <button type="submit" disabled={pending || trimmed === ''}>
      {pending ? 'Adding…' : 'Add peptide'}
    </button>
  </div>
</form>
