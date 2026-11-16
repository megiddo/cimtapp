<script lang="ts">
  import type { Person } from './types';

  type Props = {
    people: Person[];
    selectedId: string;
    disabled?: boolean;
    onselect: (id: string) => void;
    onadd: () => void;
  };

  let { people, selectedId, disabled = false, onselect, onadd }: Props = $props();
</script>

<div class="chip-row">
  {#each people as person (person.id)}
    <button
      type="button"
      class="chip"
      class:selected={person.id === selectedId}
      {disabled}
      onclick={() => onselect(person.id)}
    >
      {person.name}
    </button>
  {/each}
  <button type="button" class="chip" {disabled} onclick={onadd}>+</button>
</div>