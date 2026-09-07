import { isOpenStock, type StockRef } from './inventorySheet';

export type StockGroups<T extends StockRef> = {
  open: T[];
  archived: T[];
};

export function groupStock<T extends StockRef>(items: T[]): StockGroups<T> {
  const open: T[] = [];
  const archived: T[] = [];
  for (const item of items) {
    if (isOpenStock(item)) {
      open.push(item);
    } else {
      archived.push(item);
    }
  }
  return { open, archived };
}

export function vialDetailHref(id: string): string {
  return `/inventory/${id}`;
}

export function waterDetailHref(id: string): string {
  return `/inventory/water/${id}`;
}

export function syringeDetailHref(id: string): string {
  return `/inventory/syringes/${id}`;
}
