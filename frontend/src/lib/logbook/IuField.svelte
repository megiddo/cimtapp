<script lang="ts">
  import { stepIu } from '$lib/dose';

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

  function bump(delta: number) {
    const current = Number(value);
    const base = Number.isFinite(current) && current > 0 ? current : 0.1;
    oninput(String(stepIu(base, delta)));
  }
</script>

<div class="iu-field">
  <button type="button" class="secondary iu-step" {disabled} onclick={() => bump(-0.5)} aria-label="Decrease IU">
    −
  </button>
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
  <button type="button" class="secondary iu-step" {disabled} onclick={() => bump(0.5)} aria-label="Increase IU">
    +
  </button>
</div>

<style>
  .iu-field {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
  }
  .iu-label {
    flex: 1;
  }
  .iu-step {
    min-width: 3rem;
    margin-bottom: 0.1rem;
  }
</style>