import test from 'node:test';
import assert from 'node:assert/strict';
import { windowRange } from '../src/windowedList.js';

test('windowing renders only nearby parent cards across a long list', () => {
  assert.deepEqual(windowRange(200, 0, 560, 112), { start: 0, end: 8 });
  assert.deepEqual(windowRange(200, 5600, 560, 112), { start: 47, end: 58 });
  assert.deepEqual(windowRange(200, 22400, 560, 112), { start: 197, end: 200 });
  assert.deepEqual(windowRange(3, 22400, 336, 112), { start: 2, end: 3 });
  assert.deepEqual(windowRange(0, 0, 560, 112), { start: 0, end: 0 });
});
