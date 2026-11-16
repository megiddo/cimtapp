<script lang="ts">
  import ModalSheet from '$lib/ui/ModalSheet.svelte';

  type Props = {
    title: string;
    open: boolean;
    pending?: boolean;
    error?: string;
    onclose: () => void;
    onsave: (name: string) => void;
  };

  let { title, open, pending = false, error = '', onclose, onsave }: Props = $props();
  let name = $state('');

  function submit(event: SubmitEvent) {
    event.preventDefault();
    const trimmed = name.trim();
    if (trimmed === '') {
      return;
    }
    onsave(trimmed);
  }

  $effect(() => {
    if (!open) {
      name = '';
    }
  });
</script>

{#if open}
  <ModalSheet {title} titleId="name-modal-title" onclose={onclose}>
    <form class="auth-form" onsubmit={submit}>
      <label>
        Name
        <input bind:value={name} disabled={pending} />
        {#if error}
          <span class="field-error">{error}</span>
        {/if}
      </label>
      <button type="submit" disabled={pending || name.trim() === ''}>{pending ? 'Saving…' : 'Save'}</button>
      <button type="button" class="secondary" {disabled} onclick={onclose}>Cancel</button>
    </form>
  </ModalSheet>
{/if}