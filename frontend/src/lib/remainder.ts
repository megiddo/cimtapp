export type RemainderTone = 'default' | 'warning' | 'danger';

export function remainingFraction(remainingMg: number, peptideMg: number): number {
  if (!(peptideMg > 0)) {
    return 0;
  }
  return remainingMg / peptideMg;
}

export function remainderTone(remainingMg: number, peptideMg: number): RemainderTone {
  const fraction = remainingFraction(remainingMg, peptideMg);
  if (remainingMg <= 0 || fraction <= 0) {
    return 'danger';
  }
  if (fraction <= 0.2) {
    return 'warning';
  }
  return 'default';
}

export function remainderToneMessage(tone: RemainderTone): string | null {
  if (tone === 'danger') {
    return 'Empty — add to inventory.';
  }
  if (tone === 'warning') {
    return 'Low remainder.';
  }
  return null;
}
