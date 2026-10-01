import test from 'node:test';
import assert from 'node:assert/strict';
import { rootOptions, categoryOptions, filterListItems, listTotals } from '../src/listView.js';

const items = [
  { id: 1, sku: 'A-1', name: 'Μπλούζα', total_qty: 2, total_val: 20, root_filters: [{ id: 10, name: 'Joyce' }], category_filters: [{ id: 11, root_id: 10, root_name: 'Joyce', name: 'Αγόρι' }] },
  { id: 2, sku: 'B-2', name: 'Παντελόνι', total_qty: 3, total_val: 36, root_filters: [{ id: 20, name: 'Paco' }], category_filters: [{ id: 21, root_id: 20, root_name: 'Paco', name: 'Αγόρι' }] },
  { id: 3, sku: 'A-3', name: 'Φούτερ', total_qty: 1, total_val: 15, root_filters: [{ id: 10, name: 'Joyce' }], category_filters: [{ id: 12, root_id: 10, root_name: 'Joyce', name: 'Κορίτσι' }] },
];

test('root tabs and level-one dropdown distinguish duplicate category names', () => {
  assert.deepEqual(rootOptions(items).map(root => root.name), ['Joyce', 'Paco']);
  assert.deepEqual(categoryOptions(items, 10).map(category => category.id), [11, 12]);
  assert.deepEqual(filterListItems(items, 10, 11).map(item => item.id), [1]);
});

test('filtered subtotal includes only visible products while the full list remains separate', () => {
  assert.deepEqual(listTotals(filterListItems(items, 10)), { qty: 3, value: 35, unpriced: false });
  assert.deepEqual(listTotals(filterListItems(items, '', '', 'b-2')), { qty: 3, value: 36, unpriced: false });
  assert.deepEqual(listTotals(items), { qty: 6, value: 71, unpriced: false });
});
