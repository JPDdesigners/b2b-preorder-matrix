import test from 'node:test';
import assert from 'node:assert/strict';
import { changeQuantityWithWheel, createWheelIntent } from '../src/cellWheel.js';

function fixture(quantity = 2) {
  const store = { cart: { 10: quantity, 20: 7 }, maxQty: 9, setQuantity(id, value) { this.cart[id] = value; } };
  const event = overrides => ({ deltaY: -100, deltaX: 0, cancelable: true, prevented: false, preventDefault() { this.prevented = true; }, ...overrides });
  return { store, event, change: (e, options = {}) => changeQuantityWithWheel(e, 10, store, { desktop: true, ...options }) };
}

test('wheel up/down changes the hovered cell by one without scrolling or touching other cells', () => {
  const f = fixture();
  const up = f.event();
  assert.equal(f.change(up), true);
  assert.equal(up.prevented, true);
  assert.equal(f.store.cart[10], 3);
  const down = f.event({ deltaY: 100 });
  f.change(down);
  assert.equal(down.prevented, true);
  assert.deepEqual(f.store.cart, { 10: 2, 20: 7 });
});

test('wheel quantities stop at zero and the configured maximum', () => {
  const f = fixture(0);
  f.change(f.event({ deltaY: 100 }));
  assert.equal(f.store.cart[10], 0);
  f.store.cart[10] = 9;
  f.change(f.event());
  assert.equal(f.store.cart[10], 9);
});

test('wheel cannot edit on touch devices or while readonly, loading or saving', () => {
  for (const options of [{ desktop: false }, { disabled: true }]) {
    const f = fixture(), e = f.event();
    assert.equal(f.change(e, options), false);
    assert.equal(f.store.cart[10], 2);
    assert.equal(e.prevented, false);
  }
});

test('zoom modifiers, horizontal scrolling, zero delta and uncancelable events keep native behavior', () => {
  for (const overrides of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { deltaX: 200 }, { deltaY: 0 }, { deltaY: NaN }, { cancelable: false }]) {
    const f = fixture(), e = f.event(overrides);
    assert.equal(f.change(e), false);
    assert.equal(f.store.cart[10], 2);
    assert.equal(e.prevented, false);
  }
});

test('an empty quantity starts from zero', () => {
  const f = fixture('');
  f.change(f.event());
  assert.equal(f.store.cart[10], 1);
});

test('a scroll gesture that slides over a cell keeps scrolling and never edits it', () => {
  const f = fixture(), e = f.event();
  assert.equal(f.change(e, { scrolling: true }), false);
  assert.equal(e.prevented, false);
  assert.equal(f.store.cart[10], 2);
});

test('scroll intent latches while wheel events keep coming and releases after idle', () => {
  let t = 0;
  const intent = createWheelIntent({ idleMs: 450, now: () => t });
  assert.equal(intent.active(), false);
  intent.noteScroll(); t = 300;
  assert.equal(intent.active(), true);
  intent.noteScroll(); t = 700; // continuous inertia extends the latch
  assert.equal(intent.active(), true);
  t = 1200;
  assert.equal(intent.active(), false);
  intent.noteScroll(); intent.reset();
  assert.equal(intent.active(), false);
});
