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
