<script lang="ts">
  type Props = {
    value: string;
    disabled?: boolean;
    error?: string;
    oninput: (value: string) => void;
  };

  let { value, disabled = false, error = '', oninput }: Props = $props();

  let inputEl = $state<HTMLInputElement | null>(null);

  function onFocus() {
    inputEl?.select();
  }
</script>

<label class="iu-label">
  IU
  <input
    bind:this={inputEl}
    inputmode="decimal"
    name="iu"
    {value}
    {disabled}
    onfocus={onFocus}
    oninput={(event) => oninput((event.currentTarget as HTMLInputElement).value)}
  />
  {#if error}
    <span class="field-error">{error}</span>
  {/if}
</label>