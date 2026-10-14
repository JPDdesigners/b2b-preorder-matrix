import test from 'node:test';
import assert from 'node:assert/strict';
import { EMPTY_CONFIRM_WORD, matchesConfirmWord, needsLatinKeyboard, confirmProgress } from '../src/emptyConfirm.js';

test('only the exact word unlocks emptying, ignoring case and outer spaces', () => {
  assert.equal(EMPTY_CONFIRM_WORD, 'delete');
  assert.equal(matchesConfirmWord('delete'), true);
  assert.equal(matchesConfirmWord('  Delete '), true);
  assert.equal(matchesConfirmWord('DELETE'), true);
  for (const value of ['', 'delet', 'deletee', 'de lete', 'διαγραφή', null, undefined, 42]) assert.equal(matchesConfirmWord(value), false);
});

test('greek keyboard layout is detected so the user can switch to latin', () => {
  assert.equal(needsLatinKeyboard('δελετε'), true);
  assert.equal(needsLatinKeyboard('delete'), false);
  assert.equal(needsLatinKeyboard(''), false);
});

test('progress distinguishes partial typing from a mistake', () => {
  assert.equal(confirmProgress(''), 'empty');
  assert.equal(confirmProgress('del'), 'partial');
  assert.equal(confirmProgress('dex'), 'mismatch');
  assert.equal(confirmProgress('delete'), 'match');
});
