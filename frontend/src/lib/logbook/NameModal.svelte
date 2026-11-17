<script lang="ts">
  import ModalSheet from '$lib/ui/ModalSheet.svelte';
  import { portal } from '$lib/ui/portal';

  type Props = {
    title: string;
    titleId?: string;
    open: boolean;
    pending?: boolean;
    error?: string;
    onclose: () => void;
    onsave: (name: string) => void;
  };

  let {
    title,
    titleId = 'name-modal-title',
    open,
    pending = false,
    error = '',
    onclose,
    onsave
  }: Props = $props();
  let name = $state('');
  let dismissReady = $state(false);

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
      dismissReady = false;
      return;
    }
    dismissReady = false;
    const id = requestAnimationFrame(() => {
      dismissReady = true;
    });
    return () => cancelAnimationFrame(id);
  });
</script>

{#if open}
  <div use:portal>
    <ModalSheet
      {title}
      {titleId}
      onclose={() => {
        if (dismissReady) {
          onclose();
        }
      }}
    >
      <form class="auth-form" onsubmit={submit}>
        <label>
          Name
          <input bind:value={name} disabled={pending} autocomplete="off" />
          {#if error}
            <span class="field-error">{error}</span>
          {/if}
        </label>
        <button type="submit" disabled={pending || name.trim() === ''}>{pending ? 'Saving…' : 'Save'}</button>
        <button type="button" class="secondary" disabled={pending} onclick={onclose}>Cancel</button>
      </form>
    </ModalSheet>
  </div>
{/if}