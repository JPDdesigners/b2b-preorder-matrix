import test from 'node:test';
import assert from 'node:assert/strict';
import { applySavedListPatch, hasConfirmedListQuote, validateListPatch } from '../src/listState.js';
const hash = 'a'.repeat(64), nextHash = 'b'.repeat(64);
const row = (id, qty, value, sku = String(id)) => ({ id, name: `Product ${id}`, sku, total_qty: qty, total_val: value, root_filters: [], category_filters: [], brand_slug: 'brand', brand_name: 'Brand' });
const list = () => ({ items: [row(10, 2, 31), row(20, 3, 52.5)], quoteParents: { 10: hash, 20: hash }, quoteVersion: hash, totalQty: 5, totalVal: 83.5, price_decimals: 2, customerInfo: { name: 'Customer' }, activeBatch: null });
test('confirmed save updates only its parent and recalculates totals without mutating the previous list', () => {
  const before = list();
  const after = applySavedListPatch(before, { parentId: 10, item: row(10, 7, 108.5), quoteVersion: nextHash });
  assert.equal(after.items[1], before.items[1]);
  assert.equal(before.items[0].total_qty, 2);
  assert.equal(after.totalQty, 10); assert.equal(after.totalVal, 161);
  assert.equal(after.quoteParents[10], nextHash); assert.equal(after.quoteParents[20], hash);
  assert.equal(after.customerInfo, before.customerInfo);
  assert.equal(after.brandSummary[0].qty, 10); assert.equal(after.brandSummary[0].parents, 2);
  assert.equal(hasConfirmedListQuote(after), true);
});
test('new parent is inserted in natural SKU order and the same product is never duplicated', () => {
  let after = applySavedListPatch(list(), { parentId: 30, item: row(30, 1, 0.1, '15'), quoteVersion: hash });
  after = applySavedListPatch(after, { parentId: 30, item: row(30, 2, 0.2, '15'), quoteVersion: nextHash });
  assert.deepEqual(after.items.map(i => i.id), [10, 30, 20]);
  assert.equal(after.totalQty, 7); assert.equal(after.totalVal, 83.7);
});
test('zeroing every variation removes the parent, its quote and its contribution to all totals', () => {
  let after = applySavedListPatch(list(), { parentId: 10, item: null, quoteVersion: hash });
  assert.deepEqual(after.items.map(i => i.id), [20]); assert.equal(after.quoteParents[10], undefined);
  assert.equal(after.totalQty, 3); assert.equal(after.totalVal, 52.5);
  after = applySavedListPatch(after, { parentId: 20, item: null, quoteVersion: hash });
  assert.deepEqual(after.items, []); assert.equal(after.totalQty, 0); assert.equal(after.totalVal, 0); assert.deepEqual(after.brandSummary, []);
});
test('unavailable or unpriced retained variations cannot acquire a confirmed quote', () => {
  const after = applySavedListPatch(list(), { parentId: 10, item: { ...row(10, 5, 31), unpriced: true }, quoteVersion: '' });
  assert.equal(hasConfirmedListQuote(after), false);
  assert.equal(hasConfirmedListQuote({ ...list(), quoteParents: { 10: hash } }), false);
});
test('foreign or malformed save summaries cannot alter the visible list', () => {
  for (const patch of [null, { parentId: 20, item: row(20, 2, 31), quoteVersion: hash }, { parentId: 10, item: row(20, 2, 31), quoteVersion: hash }, { parentId: 10, item: row(10, 2, NaN), quoteVersion: hash }]) {
    assert.throws(() => validateListPatch(patch, 10), /έγκυρη ενημέρωση/);
  }
});
