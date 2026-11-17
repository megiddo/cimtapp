import { isPositiveIu, parseIuInput } from '../dose';

export function canSubmitLog(personId: string, vialId: string, iuText: string): boolean {
  if (personId === '' || vialId === '') {
    return false;
  }
  const iu = parseIuInput(iuText);
  return iu !== null && isPositiveIu(iu);
}