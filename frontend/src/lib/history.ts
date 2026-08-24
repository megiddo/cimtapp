export type HistoryUse = {
  id: string;
  used_at: string;
  iu: number;
  peptide_mg: number;
  peptide_type_name: string;
  syringe_label: string | null;
};

export type DayGroup<T extends HistoryUse = HistoryUse> = {
  day: string;
  heading: string;
  uses: T[];
};

export function localDayKey(iso: string, now: Date = new Date(iso)): string {
  const date = Number.isNaN(now.getTime()) ? new Date(iso) : now;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function formatDayHeading(dayKey: string, locale = 'en-US'): string {
  const [year, month, day] = dayKey.split('-').map((part) => Number(part));
  if (!year || !month || !day) {
    return dayKey;
  }
  const date = new Date(year, month - 1, day);
  return date.toLocaleDateString(locale, { weekday: 'short', month: 'short', day: 'numeric' });
}

export function formatTime(iso: string, locale = 'en-US'): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  return date.toLocaleTimeString(locale, { hour: 'numeric', minute: '2-digit' });
}

export function formatDateTime(iso: string, locale = 'en-US'): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return '';
  }
  return date.toLocaleString(locale, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });
}

export function groupUsesByLocalDay<T extends HistoryUse>(uses: T[], locale = 'en-US'): DayGroup<T>[] {
  const groups: DayGroup<T>[] = [];
  for (const use of uses) {
    const date = new Date(use.used_at);
    const day = localDayKey(use.used_at, date);
    const last = groups[groups.length - 1];
    if (last !== undefined && last.day === day) {
      last.uses.push(use);
      continue;
    }
    groups.push({
      day,
      heading: formatDayHeading(day, locale),
      uses: [use]
    });
  }
  return groups;
}
