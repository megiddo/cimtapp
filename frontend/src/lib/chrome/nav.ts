export type TabId = 'log' | 'inventory' | 'settings';

export type NavTab = {
  id: TabId;
  href: string;
  label: string;
  emphasized: boolean;
};

export const NAV_TABS: readonly NavTab[] = [
  { id: 'log', href: '/', label: 'Log', emphasized: true },
  { id: 'inventory', href: '/inventory', label: 'Inventory', emphasized: false },
  { id: 'settings', href: '/settings', label: 'Settings', emphasized: false }
];

export function showsTabBar(pathname: string): boolean {
  return pathname !== '/login' && !pathname.startsWith('/login/');
}

export function showsSettingsLink(_pathname: string): boolean {
  return false;
}

export function isTabActive(pathname: string, href: string): boolean {
  if (href === '/') {
    return pathname === '/' || pathname.startsWith('/log/') || pathname === '/history';
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
