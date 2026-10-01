import test from 'node:test';
import assert from 'node:assert/strict';
import { openSiteLightbox } from '../src/siteLightbox.js';

const image = { url: '/full.jpg', title: '<img src=x onerror=alert(1)>', width: 1200, height: 1600 };
function fixture() {
  const calls = [];
  const browser = { CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } }, PhotoSwipeUI_Default() {} };
  browser.PhotoSwipe = class { constructor(root, ui, items, options) { calls.push({ items, options }); } listen() {} init() {} };
  const page = { dispatchEvent: () => true, querySelector: () => ({}), activeElement: null };
  return { browser, page, calls };
}
test('site mode reuses PhotoSwipe with full-resolution image and escaped caption', () => {
  const f = fixture(); assert.equal(openSiteLightbox(image, f.browser, f.page), true);
  assert.equal(f.calls[0].items[0].src, '/full.jpg');
  assert.equal(f.calls[0].items[0].w, 1200);
  assert.equal(f.calls[0].items[0].title.includes('<img'), false);
  assert.equal(f.calls[0].options.history, false);
});
test('missing site gallery or broken library falls back to plugin dialog', () => {
  const f = fixture(); f.page.querySelector = () => null;
  assert.equal(openSiteLightbox(image, f.browser, f.page), false);
  f.page.querySelector = () => ({}); f.browser.PhotoSwipe = class { constructor() { throw new Error('incompatible'); } };
  assert.equal(openSiteLightbox(image, f.browser, f.page), false);
});
test('a theme adapter can handle the image without opening two lightboxes', () => {
  const f = fixture(); f.page.dispatchEvent = event => { assert.equal(event.type, 'b2b-matrix:open-image'); return false; };
  assert.equal(openSiteLightbox(image, f.browser, f.page), true); assert.equal(f.calls.length, 0);
});
