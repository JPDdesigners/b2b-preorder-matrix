import test from 'node:test';
import assert from 'node:assert/strict';
import { createCellTouch } from '../src/cellTouch.js';

function fixture() {
  let clock = 1000, disabled = false;
  const changes = [], edits = [];
  const input = { ownerDocument: { activeElement: null } };
  const finger = (x = 0, y = 0, identifier = 1) => ({ clientX: x, clientY: y, identifier });
  const event = (touches, changedTouches = []) => ({ touches, changedTouches, currentTarget: { querySelector: () => input }, cancelable: true, prevented: false, preventDefault() { this.prevented = true; } });
  const controller = createCellTouch({ now: () => clock, isDisabled: () => disabled, onTap: item => changes.push(item.id), onEdit: field => edits.push(field) });
  function start(id = 1) { controller.start(event([finger()]), { id }); }
  function end(id = 1, point = finger()) { const e = event([], [point]); controller.end(e, { id }); return e; }
  return { controller, start, end, event, finger, changes, edits, input, advance: ms => { clock += ms; }, disable: () => { disabled = true; } };
}

test('single and rapid repeated taps commit immediately, including across different cells', () => {
  const f = fixture();
  for (const id of [1, 2, 1, 1]) { f.start(id); f.advance(40); assert.equal(f.end(id).prevented, true); }
  assert.deepEqual(f.changes, [1, 2, 1, 1]);
  assert.equal(f.edits.length, 0);
});
test('holding at least 500ms enters editing on release without increment or reset', () => {
  const f = fixture(); f.start(); f.advance(500); f.end();
  assert.deepEqual(f.edits, [f.input]); assert.deepEqual(f.changes, []);
});
test('a 499ms press is a single tap', () => {
  const f = fixture(); f.start(); f.advance(499); f.end();
  assert.deepEqual(f.changes, [1]); assert.deepEqual(f.edits, []);
});
test('scrolling cancels both short and long press and allows native scrolling', () => {
  for (const duration of [100, 600]) {
    const f = fixture(); f.start(); f.advance(duration);
    f.controller.move(f.event([f.finger(0, 12)]));
    assert.equal(f.end().prevented, false); assert.deepEqual(f.changes, []); assert.deepEqual(f.edits, []);
  }
});
test('release movement is checked even without a delivered touchmove', () => {
  const f = fixture(); f.start(); f.end(1, f.finger(15)); assert.deepEqual(f.changes, []);
});
test('multitouch and cancel cannot mutate quantities', () => {
  const f = fixture(); f.start(); f.controller.move(f.event([f.finger(), f.finger(0, 0, 2)])); f.end();
  f.start(); f.controller.cancel(); f.end();
  f.controller.start(f.event([f.finger(), f.finger(0, 0, 2)]), { id: 1 }); f.end();
  assert.deepEqual(f.changes, []); assert.deepEqual(f.edits, []);
});
test('input editing preserves native caret gestures and never increments', () => {
  const f = fixture(); f.input.ownerDocument.activeElement = f.input;
  f.start(); assert.equal(f.end().prevented, false); assert.deepEqual(f.changes, []);
});
test('saving or loading prevents a gesture from changing quantities', () => {
  const f = fixture(); f.start(); f.disable(); f.end(); f.start(); f.end();
  assert.deepEqual(f.changes, []); assert.deepEqual(f.edits, []);
});
