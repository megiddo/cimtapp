export const DESIGN_FLOOR_PX = 360;
export const CONTENT_MAX_PX = 430;
export const MIN_TAP_PX = 48;
export const ROW_MIN_PX = 56;
export const INPUT_FONT_PX = 16;
export const TAB_BAR_HEIGHT_PX = 56;

export type TabId = 'home' | 'log' | 'vials' | 'history';

export type NavTab = {
  id: TabId;
  href: string;
  label: string;
  emphasized: boolean;
};

export const NAV_TABS: readonly NavTab[] = [
  { id: 'home', href: '/', label: 'Home', emphasized: false },
  { id: 'log', href: '/use/new', label: 'Log', emphasized: true },
  { id: 'vials', href: '/inventory', label: 'Vials', emphasized: false },
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
  if (pathname === '/inventory/new') {
    return 'Mix';
  }
  if (pathname === '/use/new' || pathname.startsWith('/use/')) {
    return 'Log';
  }
  if (pathname === '/inventory' || pathname.startsWith('/inventory/')) {
    return 'Vials';
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

  return 'CIMTapp';
}

export function backHrefForPath(pathname: string): string | null {
  if (pathname === '/inventory/new') {
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
