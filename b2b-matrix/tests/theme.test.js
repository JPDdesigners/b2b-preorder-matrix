import test from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { appearanceStyle, normalizeAppearance, PALETTES, SYSTEM_FONT } from '../src/theme.js';
import { useMatrixStore } from '../src/stores/matrixStore.js';

test('defaults inherit the theme font and retain the existing radii with indigo accents', () => {
  const style = appearanceStyle(undefined, 'Georgia, serif');
  assert.equal(style['--b2b-primary'], '#4f46e5');
  assert.equal(style['--b2b-font'], 'Georgia, serif');
  assert.equal(style['--b2b-radius-lg'], '14px');
  assert.equal(style['--b2b-radius-mobile'], '10px');
  assert.equal(style['--b2b-radius-input'], '8px');
});
test('square mode covers every decorative radius and disabling inheritance uses built-in fonts', () => {
  const style = appearanceStyle({ radius: 'square', inherit_font: false }, 'Georgia, serif');
  for (const [key, value] of Object.entries(style)) if (key.startsWith('--b2b-radius')) assert.equal(value, '0px');
  assert.equal(style['--b2b-font'], SYSTEM_FONT);
});
test('invalid settings and prototype keys cannot inject CSS', () => {
  for (const value of [null, 'invalid', { palette: 'constructor', radius: '40px' }, { palette: 'red; background:url(https://example.test)' }]) {
    assert.deepEqual(normalizeAppearance(value), { palette: 'indigo', inherit_font: true, radius: 'rounded' });
  }
});
test('all supported palettes retain white-text contrast above 4.5:1', () => {
  function luminance(hex) {
    const rgb = hex.slice(1).match(/../g).map(value => parseInt(value, 16) / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
    return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
  }
  for (const palette of Object.values(PALETTES)) assert.ok(1.05 / (luminance(palette.primary) + .05) >= 4.5);
});
test('fresh API appearance overrides cached HTML settings without changing quantities', () => {
  setActivePinia(createPinia());
  globalThis.localStorage = { getItem: () => null };
  const store = useMatrixStore();
  store.initFromDOM({ dataset: { appearance: '{"palette":"blue","radius":"rounded"}' } });
  assert.equal(store.appearance.palette, 'blue');
  store.applyMatrixData({ colors: ['black'], sizes: ['M'], matrix: { black: { M: { id: 1, price: 10 } } }, existing_quantities: { 1: 5 }, appearance: { palette: 'violet', radius: 'square', inherit_font: false } });
  assert.deepEqual(store.appearance, { palette: 'violet', radius: 'square', inherit_font: false });
  assert.equal(store.cart[1], 5);
});
test('custom hex color palette produces dynamic primary, hover, soft, border, and contrast tokens', () => {
  const style = appearanceStyle({ palette: 'custom', custom_color: '#10b981' });
  assert.equal(style['--b2b-primary'], '#10b981');
  assert.ok(style['--b2b-primary-hover']);
  assert.ok(style['--b2b-primary-soft']);
  assert.ok(style['--b2b-primary-border']);
  assert.equal(style['--b2b-on-primary'], '#0f172a');
  assert.equal(style['--b2b-focus-ring'], '#334155');
});
test('pale and bright custom colors switch to dark foreground and visible focus ring while dark colors keep white foreground', () => {
  const pale = appearanceStyle({ palette: 'custom', custom_color: '#fef08a' });
  assert.equal(pale['--b2b-on-primary'], '#0f172a');
  assert.equal(pale['--b2b-focus-ring'], '#334155');
  assert.notEqual(pale['--b2b-primary-ink'], '#fef08a');

  const white = appearanceStyle({ palette: 'custom', custom_color: '#ffffff' });
  assert.equal(white['--b2b-on-primary'], '#0f172a');
  assert.equal(white['--b2b-focus-ring'], '#334155');
  assert.equal(white['--b2b-primary-soft'], '#f1f5f9');

  const dark = appearanceStyle({ palette: 'custom', custom_color: '#1e3a8a' });
  assert.equal(dark['--b2b-on-primary'], '#ffffff');
  assert.equal(dark['--b2b-focus-ring'], '#1e3a8a');
  assert.equal(dark['--b2b-primary-ink'], '#1e3a8a');
});

