<script lang="ts">
  type Props = {
    value: string;
    options: string[];
    disabled?: boolean;
    oninput: (value: string) => void;
  };

  let { value, options, disabled = false, oninput }: Props = $props();

  const datalistId = 'lid-colors';

  const showAdd = $derived(
    value.trim() !== '' && !options.some((item) => item.toLowerCase() === value.trim().toLowerCase())
  );
</script>

<label>
  Lid color
  <input
    list={datalistId}
    {value}
    {disabled}
    oninput={(event) => oninput((event.currentTarget as HTMLInputElement).value)}
  />
  <datalist id={datalistId}>
    {#each options as option (option)}
      <option value={option}></option>
    {/each}
  </datalist>
  {#if showAdd}
    <span class="muted">Add "{value.trim()}"</span>
  {/if}
</label>