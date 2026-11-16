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
  });
});

describe('NAV_TABS', () => {
  it('lists three tabs with Log emphasized at home', () => {
    expect(NAV_TABS.map((tab) => tab.id)).toEqual(['log', 'inventory', 'settings']);
    expect(NAV_TABS[0]).toEqual(emphasizedTab());
  });
});

describe('showsTabBar', () => {
  it('hides tabs on login', () => {
    expect(showsTabBar('/login')).toBe(false);
    expect(showsTabBar('/')).toBe(true);
  });
});

describe('isTabActive', () => {
  it('marks log tab for / and /log edits', () => {
    expect(isTabActive('/', '/')).toBe(true);
    expect(isTabActive('/log/x', '/')).toBe(true);
    expect(isTabActive('/inventory', '/inventory')).toBe(true);
  });
});

describe('titleForPath', () => {
  it('names v2 screens', () => {
    expect(titleForPath('/')).toBe('Log');
    expect(titleForPath('/inventory')).toBe('Inventory');
    expect(titleForPath('/settings')).toBe('Settings');
    expect(titleForPath('/log/1')).toBe('Edit log');
  });
});

describe('needsStickyCta', () => {
  it('is true on log routes only', () => {
    expect(needsStickyCta('/')).toBe(true);
    expect(needsStickyCta('/log/1')).toBe(true);
    expect(needsStickyCta('/inventory')).toBe(false);
  });
});

describe('backHrefForPath', () => {
  it('returns home only when editing a log row', () => {
    expect(backHrefForPath('/')).toBeNull();
    expect(backHrefForPath('/log/1')).toBe('/');
    expect(backHrefForPath('/unknown')).toBeNull();
  });
});

describe('showsSettingsLink', () => {
  it('is always false because Settings is a tab', () => {
    expect(showsSettingsLink('/')).toBe(false);
  });
});