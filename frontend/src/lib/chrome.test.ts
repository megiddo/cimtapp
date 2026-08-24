import { describe, expect, it } from 'vitest';
import {
  CONTENT_MAX_PX,
  DESIGN_FLOOR_PX,
  INPUT_FONT_PX,
  backHrefForPath,
  emphasizedTab,
  emphasizedTabFrom,
  isTabActive,
  MIN_TAP_PX,
  NAV_TABS,
  needsStickyCta,
  ROW_MIN_PX,
  showsSettingsLink,
  showsTabBar,
  TAB_BAR_HEIGHT_PX,
  titleForPath
} from './chrome';

describe('chrome layout tokens', () => {
  it('uses the 360px design floor and 430px max column', () => {
    expect(DESIGN_FLOOR_PX).toBe(360);
    expect(CONTENT_MAX_PX).toBe(430);
    expect(MIN_TAP_PX).toBe(48);
    expect(ROW_MIN_PX).toBe(56);
    expect(INPUT_FONT_PX).toBe(16);
    expect(TAB_BAR_HEIGHT_PX).toBe(56);
    expect(CONTENT_MAX_PX).toBeGreaterThan(DESIGN_FLOOR_PX);
  });
});

describe('NAV_TABS', () => {
  it('lists four tabs with Log emphasized in the center', () => {
    expect(NAV_TABS.map((tab) => tab.id)).toEqual(['home', 'log', 'inventory', 'history']);
    expect(NAV_TABS.map((tab) => tab.href)).toEqual(['/', '/use/new', '/inventory', '/history']);
    expect(NAV_TABS.map((tab) => tab.label)).toEqual(['Home', 'Log', 'Inventory', 'History']);
    expect(NAV_TABS.filter((tab) => tab.emphasized)).toEqual([
      { id: 'log', href: '/use/new', label: 'Log', emphasized: true }
    ]);
    expect(NAV_TABS[1]).toEqual(emphasizedTab());
    expect(() => emphasizedTabFrom([])).toThrow('No emphasized tab configured');
    expect(() =>
      emphasizedTabFrom([{ id: 'home', href: '/', label: 'Home', emphasized: false }])
    ).toThrow('No emphasized tab configured');
  });
});

describe('showsTabBar', () => {
  it('hides tabs on login and shows them on app routes', () => {
    expect(showsTabBar('/login')).toBe(false);
    expect(showsTabBar('/login/reset')).toBe(false);
    expect(showsTabBar('/')).toBe(true);
    expect(showsTabBar('/use/new')).toBe(true);
    expect(showsTabBar('/inventory')).toBe(true);
    expect(showsTabBar('/history')).toBe(true);
    expect(showsTabBar('/settings')).toBe(true);
    expect(showsTabBar('/login-help')).toBe(true);
  });
});

describe('showsSettingsLink', () => {
  it('is only on Home', () => {
    expect(showsSettingsLink('/')).toBe(true);
    expect(showsSettingsLink('/settings')).toBe(false);
    expect(showsSettingsLink('/inventory')).toBe(false);
    expect(showsSettingsLink('/login')).toBe(false);
  });
});

describe('isTabActive', () => {
  it('matches Home only at the root', () => {
    expect(isTabActive('/', '/')).toBe(true);
    expect(isTabActive('/inventory', '/')).toBe(false);
    expect(isTabActive('/history', '/')).toBe(false);
  });

  it('matches nested paths for non-home tabs', () => {
    expect(isTabActive('/use/new', '/use/new')).toBe(true);
    expect(isTabActive('/inventory', '/inventory')).toBe(true);
    expect(isTabActive('/inventory/new', '/inventory')).toBe(true);
    expect(isTabActive('/inventory/abc', '/inventory')).toBe(true);
    expect(isTabActive('/history/edit', '/history')).toBe(true);
    expect(isTabActive('/inventory', '/history')).toBe(false);
    expect(isTabActive('/use', '/use/new')).toBe(false);
  });
});

describe('titleForPath', () => {
  it('returns the chrome title for known routes', () => {
    expect(titleForPath('/')).toBe('Home');
    expect(titleForPath('/use/new')).toBe('Log');
    expect(titleForPath('/use/123')).toBe('Log');
    expect(titleForPath('/inventory')).toBe('Inventory');
    expect(titleForPath('/inventory/new')).toBe('Add');
    expect(titleForPath('/inventory/water/new')).toBe('Add BAC');
    expect(titleForPath('/inventory/water/abc')).toBe('Edit');
    expect(titleForPath('/inventory/syringes/new')).toBe('Add syringe');
    expect(titleForPath('/inventory/syringes/abc')).toBe('Edit');
    expect(titleForPath('/inventory/peptides/new')).toBe('Add peptide');
    expect(titleForPath('/inventory/abc')).toBe('Edit');
    expect(titleForPath('/history')).toBe('History');
    expect(titleForPath('/history/1')).toBe('Edit');
    expect(titleForPath('/login')).toBe('Sign in');
    expect(titleForPath('/login/x')).toBe('Sign in');
    expect(titleForPath('/settings')).toBe('Settings');
    expect(titleForPath('/settings/syringes')).toBe('Settings');
    expect(titleForPath('/unknown')).toBe('PepCalc');
  });
});

describe('backHrefForPath', () => {
  it('returns a chevron target for mix, vial edit, and use edit', () => {
    expect(backHrefForPath('/inventory/new')).toBe('/inventory');
    expect(backHrefForPath('/inventory/water/new')).toBe('/inventory');
    expect(backHrefForPath('/inventory/water/abc')).toBe('/inventory');
    expect(backHrefForPath('/inventory/syringes/new')).toBe('/inventory');
    expect(backHrefForPath('/inventory/syringes/abc')).toBe('/inventory');
    expect(backHrefForPath('/inventory/peptides/new')).toBe('/inventory');
    expect(backHrefForPath('/inventory/abc')).toBe('/inventory');
    expect(backHrefForPath('/history/abc')).toBe('/history');
    expect(backHrefForPath('/history')).toBeNull();
    expect(backHrefForPath('/inventory')).toBeNull();
    expect(backHrefForPath('/')).toBeNull();
  });
});

describe('needsStickyCta', () => {
  it('is true on log, mix, inventory, vial edit, and use edit', () => {
    expect(needsStickyCta('/use/new')).toBe(true);
    expect(needsStickyCta('/inventory')).toBe(true);
    expect(needsStickyCta('/inventory/new')).toBe(true);
    expect(needsStickyCta('/inventory/water/new')).toBe(true);
    expect(needsStickyCta('/inventory/water/abc')).toBe(true);
    expect(needsStickyCta('/inventory/syringes/new')).toBe(true);
    expect(needsStickyCta('/inventory/syringes/abc')).toBe(true);
    expect(needsStickyCta('/inventory/peptides/new')).toBe(true);
    expect(needsStickyCta('/inventory/abc')).toBe(true);
    expect(needsStickyCta('/history/abc')).toBe(true);
    expect(needsStickyCta('/history')).toBe(false);
    expect(needsStickyCta('/')).toBe(false);
  });
});
