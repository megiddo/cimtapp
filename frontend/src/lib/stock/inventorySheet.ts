export type StockRef = { id: string; archived_at: string | null };

export function isOpenStock(item: StockRef): boolean {
  return item.archived_at === null;
}

export function openStockOnly<T extends StockRef>(items: T[]): T[] {
  return items.filter(isOpenStock);
}

export function sheetShowsArchivedId(items: StockRef[]): boolean {
  return items.some((item) => item.archived_at !== null);
}
