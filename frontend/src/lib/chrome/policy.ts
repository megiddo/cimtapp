export type RoutePolicy = {
  test: (pathname: string) => boolean;
  title: string;
  backHref: string | null;
  stickyCta: boolean;
};

export const ROUTE_POLICIES: readonly RoutePolicy[] = [
  { test: (pathname) => pathname === '/', title: 'Log', backHref: null, stickyCta: true },
  { test: (pathname) => /^\/log\/[^/]+$/.test(pathname), title: 'Edit log', backHref: '/', stickyCta: true },
  { test: (pathname) => pathname === '/inventory', title: 'Inventory', backHref: null, stickyCta: false },
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