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

export const NAV_TABS: readonly NavTab[] = [
  { id: 'home', href: '/', label: 'Home', emphasized: false },
  { id: 'log', href: '/use/new', label: 'Log', emphasized: true },
  { id: 'inventory', href: '/inventory', label: 'Inventory', emphasized: false },
  { id: 'history', href: '/history', label: 'History', emphasized: false }
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

export function titleForPath(pathname: string): string {
  if (pathname === '/') {
    return 'Home';
  }
  if (pathname === '/use/new' || pathname.startsWith('/use/')) {
    return 'Log';
  }
  if (pathname === '/inventory/new') {
    return 'Add';
  }
  if (pathname === '/inventory/water/new') {
    return 'Add BAC';
  }
  if (pathname === '/inventory/syringes/new') {
    return 'Add syringe';
  }
  if (pathname === '/inventory/peptides/new') {
    return 'Add peptide';
  }
  if (
    /^\/inventory\/water\/[^/]+$/.test(pathname) ||
    /^\/inventory\/syringes\/[^/]+$/.test(pathname) ||
    /^\/inventory\/[^/]+$/.test(pathname)
  ) {
    return 'Edit';
  }
  if (pathname === '/inventory' || pathname.startsWith('/inventory/')) {
    return 'Inventory';
  }
  if (/^\/history\/[^/]+$/.test(pathname)) {
    return 'Edit';
  }
  if (pathname === '/history' || pathname.startsWith('/history/')) {
    return 'History';
  }
  if (pathname === '/login' || pathname.startsWith('/login/')) {
    return 'Sign in';
  }
  if (pathname === '/settings' || pathname.startsWith('/settings/')) {
    return 'Settings';
  }

  return 'PepTrack';
}

export function backHrefForPath(pathname: string): string | null {
  if (
    pathname === '/inventory/new' ||
    pathname === '/inventory/water/new' ||
    pathname === '/inventory/syringes/new' ||
    pathname === '/inventory/peptides/new' ||
    /^\/inventory\/water\/[^/]+$/.test(pathname) ||
    /^\/inventory\/syringes\/[^/]+$/.test(pathname) ||
    /^\/inventory\/[^/]+$/.test(pathname)
  ) {
    return '/inventory';
  }
  if (/^\/history\/[^/]+$/.test(pathname)) {
    return '/history';
  }

  return null;
}

export function needsStickyCta(pathname: string): boolean {
  return (
    pathname === '/use/new' ||
    pathname === '/inventory' ||
    pathname === '/inventory/new' ||
    pathname === '/inventory/water/new' ||
    pathname === '/inventory/syringes/new' ||
    pathname === '/inventory/peptides/new' ||
    /^\/inventory\/water\/[^/]+$/.test(pathname) ||
    /^\/inventory\/syringes\/[^/]+$/.test(pathname) ||
    /^\/inventory\/[^/]+$/.test(pathname) ||
    /^\/history\/[^/]+$/.test(pathname)
  );
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
