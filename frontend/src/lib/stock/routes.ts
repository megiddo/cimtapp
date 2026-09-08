export const INVENTORY_FULL_LIST_ROUTES = [
  { path: '/inventory/vials', title: 'Vials', kind: 'vials' },
  { path: '/inventory/water', title: 'BAC', kind: 'water' },
  { path: '/inventory/syringes', title: 'Syringes', kind: 'syringes' }
] as const;

export type InventoryFullListKind = (typeof INVENTORY_FULL_LIST_ROUTES)[number]['kind'];

export function isInventoryFullListPath(pathname: string): boolean {
  return INVENTORY_FULL_LIST_ROUTES.some((route) => route.path === pathname);
}

export function fullListHref(kind: InventoryFullListKind): string {
  const route = INVENTORY_FULL_LIST_ROUTES.find((item) => item.kind === kind);
  return route?.path ?? '/inventory';
}
