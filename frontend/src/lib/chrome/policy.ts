import { INVENTORY_FULL_LIST_ROUTES } from '../stock/routes';

export type RoutePolicy = {
  test: (pathname: string) => boolean;
  title: string;
  backHref: string | null;
  stickyCta: boolean;
};

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
