import { INVENTORY_FULL_LIST_ROUTES } from './stock/routes';

export const DESIGN_FLOOR_PX = 360;
export const CONTENT_MAX_PX = 430;
export const MIN_TAP_PX = 48;
export const ROW_MIN_PX = 56;
export const INPUT_FONT_PX = 16;
export const TAB_BAR_HEIGHT_PX = 56;

export type TabId = 'home' | 'log' | 'inventory' | 'history';

export type NavTab = {
  id: TabId;
  href: string;
  label: string;
  emphasized: boolean;
};

export type RoutePolicy = {
  test: (pathname: string) => boolean;
  title: string;
  backHref: string | null;
  stickyCta: boolean;
};

export const NAV_TABS: readonly NavTab[] = [
  { id: 'home', href: '/', label: 'Home', emphasized: false },
  { id: 'log', href: '/use/new', label: 'Log', emphasized: true },
  { id: 'inventory', href: '/inventory', label: 'Inventory', emphasized: false },
  { id: 'history', href: '/history', label: 'History', emphasized: false }
];

const FULL_LIST_POLICIES: RoutePolicy[] = INVENTORY_FULL_LIST_ROUTES.map((route) => ({
  test: (pathname: string) => pathname === route.path,
  title: route.title,
  backHref: '/inventory',
  stickyCta: false
}));

export const ROUTE_POLICIES: readonly RoutePolicy[] = [
  { test: (pathname) => pathname === '/', title: 'Home', backHref: null, stickyCta: false },
  { test: (pathname) => pathname === '/use/new', title: 'Log', backHref: null, stickyCta: true },
  { test: (pathname) => pathname.startsWith('/use/'), title: 'Log', backHref: null, stickyCta: false },
  { test: (pathname) => pathname === '/inventory/new', title: 'Add', backHref: '/inventory', stickyCta: true },
  { test: (pathname) => pathname === '/inventory/water/new', title: 'Add BAC', backHref: '/inventory', stickyCta: true },
  { test: (pathname) => pathname === '/inventory/syringes/new', title: 'Add syringe', backHref: '/inventory', stickyCta: true },
  { test: (pathname) => pathname === '/inventory/peptides/new', title: 'Add peptide', backHref: '/inventory', stickyCta: true },
  ...FULL_LIST_POLICIES,
  {
    test: (pathname) => /^\/inventory\/water\/[^/]+$/.test(pathname),
    title: 'Edit',
    backHref: '/inventory',
    stickyCta: true
  },
  {
    test: (pathname) => /^\/inventory\/syringes\/[^/]+$/.test(pathname),
    title: 'Edit',
    backHref: '/inventory',
    stickyCta: true
  },
  {
    test: (pathname) => /^\/inventory\/[^/]+$/.test(pathname),
    title: 'Edit',
    backHref: '/inventory',
    stickyCta: true
  },
  { test: (pathname) => pathname === '/inventory', title: 'Inventory', backHref: null, stickyCta: true },
  { test: (pathname) => pathname.startsWith('/inventory/'), title: 'Inventory', backHref: null, stickyCta: false },
  { test: (pathname) => /^\/history\/[^/]+$/.test(pathname), title: 'Edit', backHref: '/history', stickyCta: true },
  { test: (pathname) => pathname === '/history' || pathname.startsWith('/history/'), title: 'History', backHref: null, stickyCta: false },
  { test: (pathname) => pathname === '/login' || pathname.startsWith('/login/'), title: 'Sign in', backHref: null, stickyCta: false },
  { test: (pathname) => pathname === '/settings' || pathname.startsWith('/settings/'), title: 'Settings', backHref: null, stickyCta: false }
];

export function showsTabBar(pathname: string): boolean {
  return pathname !== '/login' && !pathname.startsWith('/login/');
}

export function showsSettingsLink(pathname: string): boolean {
  return pathname === '/';
}

export function isTabActive(pathname: string, href: string): boolean {
  if (href === '/') {
    return pathname === '/';
  }

  return pathname === href || pathname.startsWith(`${href}/`);
}

function policyFor(pathname: string): RoutePolicy | undefined {
  return ROUTE_POLICIES.find((policy) => policy.test(pathname));
}

export function titleForPath(pathname: string): string {
  return policyFor(pathname)?.title ?? 'PepTrack';
}

export function backHrefForPath(pathname: string): string | null {
  return policyFor(pathname)?.backHref ?? null;
}

export function needsStickyCta(pathname: string): boolean {
  return policyFor(pathname)?.stickyCta === true;
}

export function emphasizedTabFrom(tabs: readonly NavTab[]): NavTab {
  const tab = tabs.find((item) => item.emphasized);
  if (tab === undefined) {
    throw new Error('No emphasized tab configured');
  }

  return tab;
}

export function emphasizedTab(): NavTab {
  return emphasizedTabFrom(NAV_TABS);
}
