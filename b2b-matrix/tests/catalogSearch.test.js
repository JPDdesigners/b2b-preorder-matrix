import test from 'node:test';
import assert from 'node:assert/strict';
import { exactCatalogProduct, canSearchCatalog, catalogWithMembership, catalogPanelPlacement } from '../src/catalogSearch.js';

test('numeric queries need four digits, while names and mixed SKUs need two characters', () => {
  for (const text of ['', ' ', '1', '12', '123', 'α', 'A']) assert.equal(canSearchCatalog(text), false);
  for (const text of ['1234', ' 2413851 ', 'σε', 'A1', '24-', 'Φούτερ']) assert.equal(canSearchCatalog(text), true);
});

test('membership follows additions and removals without reading or calculating quantities', () => {
  const products = [{ id: 101, in_list: false }, { id: 102, in_list: true }];
  assert.deepEqual(catalogWithMembership(products, new Set([101])).map(p => p.in_list), [true, false]);
  assert.deepEqual(catalogWithMembership(products, new Set([101, 102])).map(p => p.in_list), [true, true]);
  assert.equal(catalogWithMembership(products, new Set())[0].in_list, false);
  assert.equal(products[0].in_list, false);
});

test('portrait tablet results sit to the left and stay above the keyboard viewport boundary', () => {
  const anchor = { left: 432, top: 110, bottom: 154, width: 330 };
  const panel = catalogPanelPlacement(anchor, { left: 0, top: 0, width: 820, height: 440 }, true);
  assert.equal(panel.mode, 'side');
  assert.ok(panel.left + panel.width < anchor.left);
  assert.ok(panel.top < anchor.top);
  assert.ok(panel.top >= 12 && panel.top + panel.maxHeight <= 428);
});

test('narrow screens use space above the input when the keyboard covers space below', () => {
  const anchor = { left: 16, top: 230, bottom: 274, width: 358 };
  const panel = catalogPanelPlacement(anchor, { left: 0, top: 20, width: 390, height: 300 }, true);
  assert.equal(panel.mode, 'above');
  assert.ok(panel.top >= 32 && panel.top + panel.maxHeight < anchor.top);
  assert.ok(panel.left >= 12 && panel.left + panel.width <= 378);
});

test('desktop placement remains below and recalculates for scrolling and viewport offsets', () => {
  const anchor = { left: 840, top: 100, bottom: 144, width: 400 };
  const desktop = catalogPanelPlacement(anchor, { left: 0, top: 0, width: 1440, height: 900 });
  assert.equal(desktop.mode, 'below');
  assert.equal(desktop.top, 154);
  const shifted = catalogPanelPlacement({ ...anchor, left: 360, top: 450, bottom: 494 }, { left: 100, top: 200, width: 600, height: 320 }, true);
  assert.equal(shifted.mode, 'above');
  assert.ok(shifted.left >= 112 && shifted.left + shifted.width <= 688);
  assert.ok(shifted.top >= 212 && shifted.top + shifted.maxHeight <= 508);
});

const exact = { id: 101, sku: '2413851', matched_sku: '2413851', sku_match: 'exact' };
test('Enter selects the exact SKU even when a prefix or title is ahead of it', () => {
  const results = [{ id: 102, sku: '24138510', sku_match: 'prefix' }, { id: 103, sku: 'OTHER', sku_match: 'title' }, exact];
  assert.equal(exactCatalogProduct(results, ' 2413851 '), exact);
  assert.equal(exactCatalogProduct(results, '241385'), null);
});
test('a variation SKU opens its parent even when the parent SKU differs', () => {
  const variation = { ...exact, sku: 'PARENT', matched_sku: 'VAR-BLUE-L' };
  assert.equal(exactCatalogProduct([variation], 'var-blue-l'), variation);
});
test('ambiguous exact SKUs cannot open an arbitrary parent', () => {
  assert.equal(exactCatalogProduct([exact, { ...exact, id: 102 }], '2413851'), null);
  assert.equal(exactCatalogProduct([exact, { ...exact }], '2413851')?.id, 101);
});
test('stale results for the previous digit do not match the current query', () => {
  assert.equal(exactCatalogProduct([exact], '2413853'), null);
  assert.equal(exactCatalogProduct([exact], ''), null);
});
test('title-only and partial matches cannot trigger automatic opening', () => {
  for (const sku_match of ['prefix', 'contains', 'title', undefined]) {
    assert.equal(exactCatalogProduct([{ ...exact, sku_match }], '2413851'), null);
  }
  assert.equal(exactCatalogProduct([{ ...exact, id: '101' }], '2413851'), null);
});
