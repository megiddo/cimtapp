export type InventoryListResource = 'syringes' | 'compounds' | 'bac-bottles';
export type InventoryListView = 'all';

export function inventoryListPath(resource: InventoryListResource, view?: InventoryListView): string {
  const path = `/api/v1/${resource}`;
  return view === 'all' ? `${path}?view=all` : path;
}
