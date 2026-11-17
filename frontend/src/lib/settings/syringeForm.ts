export function isValidSyringe(volumeMl: number, capacityIu: number): boolean {
  return Number.isFinite(volumeMl) && volumeMl > 0 && Number.isFinite(capacityIu) && capacityIu > 0;
}