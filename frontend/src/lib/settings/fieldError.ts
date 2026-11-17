import { firstFieldError, type FieldMap } from '../payload';

export function nameFieldError(fields: FieldMap): string {
  return firstFieldError(fields, 'name') ?? '';
}