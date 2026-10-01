import test from 'node:test';
import assert from 'node:assert/strict';
import { displayColorName } from '../src/colorNames.js';

test('uppercase catalog color names display in normal title case', () => {
  assert.equal(displayColorName('BLACK'), 'Black');
  assert.equal(displayColorName('NAVY BLUE'), 'Navy Blue');
  assert.equal(displayColorName('ΜΑΥΡΟ'), 'Μαυρο');
  assert.equal(displayColorName('Navy Blue'), 'Navy Blue');
  assert.equal(displayColorName(''), '');
});
