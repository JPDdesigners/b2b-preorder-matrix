import test from 'node:test';
import assert from 'node:assert/strict';
import { topObstruction, stickyOffsetOverride } from '../src/stickyOffset.js';

function element(name, { position = 'static', rect = null, parent = null } = {}) {
  return { name, position, parentElement: parent, rect, getBoundingClientRect() { return this.rect; } };
}
function env(hitsByX, width = 1000, height = 800) {
  const body = element('body');
  const doc = { body, documentElement: element('html'), elementsFromPoint: x => hitsByX(x) };
  const win = { innerWidth: width, innerHeight: height, getComputedStyle: el => ({ position: el.position }) };
  return { body, doc, win };
}

test('docks under a full-width fixed theme header and the admin bar', () => {
  const { body, doc, win } = env(() => hits);
  const header = element('header', { position: 'fixed', rect: { top: 0, bottom: 92, width: 1000, height: 92 }, parent: body });
  const logo = element('logo', { parent: header });
  const hits = [logo, body];
  assert.equal(topObstruction({ doc, win }), 92);
});

test('ignores our own elements, narrow sticky widgets and oversized overlays', () => {
  const { body, doc, win } = env(() => hits);
  const root = { contains: el => el.name === 'ours' };
  const ours = element('ours', { position: 'sticky', rect: { top: 0, bottom: 46, width: 1000, height: 46 }, parent: body });
  const widget = element('widget', { position: 'sticky', rect: { top: -10, bottom: 300, width: 250, height: 310 }, parent: body });
  const overlay = element('overlay', { position: 'fixed', rect: { top: 0, bottom: 800, width: 1000, height: 800 }, parent: body });
  const hits = [ours, widget, overlay, body];
  assert.equal(topObstruction({ root, doc, win }), 0);
});

test('no chrome at the top means offset zero', () => {
  const { body, doc, win } = env(() => [body]);
  assert.equal(topObstruction({ doc, win }), 0);
});

test('theme override must be a pixel length', () => {
  const win = value => ({ getComputedStyle: () => ({ getPropertyValue: () => value }) });
  assert.equal(stickyOffsetOverride({}, win(' 88px ')), 88);
  assert.equal(stickyOffsetOverride({}, win('')), null);
  assert.equal(stickyOffsetOverride({}, win('5rem')), null);
});
