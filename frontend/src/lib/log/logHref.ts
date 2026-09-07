export function logHref(init: { profileId?: string; compoundId?: string; iu?: string | number } = {}): string {
  const params = new URLSearchParams();
  if (init.profileId !== undefined && init.profileId !== '') {
    params.set('profile_id', init.profileId);
  }
  if (init.compoundId !== undefined && init.compoundId !== '') {
    params.set('compound_id', init.compoundId);
  }
  if (init.iu !== undefined && init.iu !== '') {
    params.set('iu', String(init.iu));
  }
  const query = params.toString();
  return query === '' ? '/use/new' : `/use/new?${query}`;
}
