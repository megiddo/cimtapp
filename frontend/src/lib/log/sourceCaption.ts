export function formatSourceCaption(sourceLoggedAt: string | null): string | null {
  if (sourceLoggedAt === null || sourceLoggedAt === '') {
    return null;
  }
  const date = new Date(sourceLoggedAt.includes('T') ? sourceLoggedAt : `${sourceLoggedAt}T12:00:00`);
  if (Number.isNaN(date.getTime())) {
    return null;
  }
  const label = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  return `Same as ${label}`;
}