import { waterNeededPerBottle } from '../dose';

export function pepcalcBacWater(
  peptideMg: number,
  targetMg: number,
  targetIu: number,
  syringeVolumeMl: number,
  syringeCapacityIu: number
): number | null {
  return waterNeededPerBottle(peptideMg, targetMg, targetIu, syringeVolumeMl, syringeCapacityIu);
}