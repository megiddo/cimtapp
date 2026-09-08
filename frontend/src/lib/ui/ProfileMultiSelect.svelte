<script lang="ts">
  import { firstFieldError, type FieldMap } from '$lib/payload';
  import type { Profile } from '$lib/repositories/types';

  type Props = {
    profiles: Profile[];
    selectedIds: string[];
    fields: FieldMap;
    ontoggle: (id: string) => void;
  };

  let { profiles, selectedIds, fields, ontoggle }: Props = $props();
</script>

<fieldset class="check-list">
  <legend>Profiles</legend>
  {#each profiles as profile (profile.id)}
    <label class="check-row">
      <input type="checkbox" checked={selectedIds.includes(profile.id)} onchange={() => ontoggle(profile.id)} />
      <span>{profile.name}{profile.is_default ? ' (default)' : ''}</span>
    </label>
  {/each}
  {#if firstFieldError(fields, 'profile_ids')}
    <span class="field-error">{firstFieldError(fields, 'profile_ids')}</span>
  {/if}
</fieldset>
