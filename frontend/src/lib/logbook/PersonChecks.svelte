<script lang="ts">
  import type { Person } from './types';

  type Props = {
    people: Person[];
    selectedIds: string[];
    disabled?: boolean;
    onchange: (ids: string[]) => void;
  };

  let { people, selectedIds, disabled = false, onchange }: Props = $props();

  function toggle(id: string) {
    if (disabled) {
      return;
    }
    if (selectedIds.includes(id)) {
      onchange(selectedIds.filter((item) => item !== id));
      return;
    }
    onchange([...selectedIds, id]);
  }
</script>

<div class="check-grid">
  {#each people as person (person.id)}
    <label class="check">
      <input
        type="checkbox"
        checked={selectedIds.includes(person.id)}
        {disabled}
        onchange={() => toggle(person.id)}
      />
      {person.name}
    </label>
  {/each}
</div>