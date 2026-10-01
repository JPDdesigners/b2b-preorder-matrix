import test, { beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { createPinia, setActivePinia } from 'pinia';
import { useMatrixStore, requestJSON } from '../src/stores/matrixStore.js';

const payload = () => ({ colors: ['black'], sizes: ['S', 'M', 'L'], matrix: { black: { S: { id: 10, price: 15.5 }, M: { id: 11, price: 16.5 }, L: { id: 12, price: 17.5 } } }, existing_quantities: { 10: 2, 11: 1 }, currency: 'EUR' });
const originalFetch = globalThis.fetch;
let store;
let calls;
function reply(body, status = 200) { return { ok: status >= 200 && status < 300, status, json: async () => body }; }
function transport(saveBody, status = 200) {
  globalThis.fetch = async (url, options) => {
    calls.push({ url: String(url), options });
    if (String(url).includes('b2b_matrix_session')) return reply({ success: true, data: { user_id: 1, nonce: 'fresh_nonce' } });
    return reply(saveBody, status);
  };
}
beforeEach(() => {
  setActivePinia(createPinia());
  const storage = new Map();
  globalThis.localStorage = { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) };
  globalThis.window = { location: { origin: 'https://shop.test' } };
  store = useMatrixStore();
  store.applyMatrixData(payload());
  store.apiEndpoint = '/wp-json/b2b/v1/matrix';
  store.sessionEndpoint = '/wp-admin/admin-ajax.php';
  store.productId = 100;
  store.userId = 1;
  calls = [];
});
afterEach(() => { globalThis.fetch = originalFetch; });

test('prefill, differential prices and equal-quantity price change', () => {
  assert.equal(store.totalQty, 3);
  assert.equal(store.currentTotalPriceRaw, 47.5);
  assert.equal(store.isDirty, false);
  store.setQuantity(10, 1); store.setQuantity(12, 1);
  assert.equal(store.deltaQty, 0);
  assert.equal(store.deltaPriceRaw, 2);
  assert.equal(store.isDirty, true);
});
test('row minus follows selected curve, skips absent cells and clamps at zero', () => {
  store.setCustomCurve(['S', 'L']);
  store.incrementRow('black', -1);
  assert.deepEqual({ ...store.cart }, { 10: 1, 11: 1, 12: 0 });
  store.incrementRow('black', -1); store.incrementRow('black', -1);
  assert.equal(store.cart[10], 0); assert.equal(store.cart[11], 1);
  delete store.data.matrix.black.L;
  store.incrementRow('black', 1);
  assert.equal(store.cart[10], 1);
});
test('a curve saved for another size system resets to one per size', () => {
  localStorage.setItem('b2b_matrix_custom_curve', JSON.stringify(['42', '44']));
  store.initCustomCurve();
  assert.equal(store.curveResetNotice, true);
  assert.equal(store.customCurve, null);
  assert.deepEqual(store.activeCurveSizes, ['S', 'M', 'L']);
  assert.equal(localStorage.getItem('b2b_matrix_custom_curve'), null);
  store.incrementRow('black', 1);
  assert.deepEqual({ ...store.cart }, { 10: 3, 11: 2, 12: 1 });
});
test('reselecting every size restores the default row, including after reload', () => {
  store.setCustomCurve(['M', 'L']);
  assert.equal(store.isCustomCurveActive, true);
  store.initCustomCurve();
  assert.deepEqual(store.activeCurveSizes, ['M', 'L']);
  store.setCustomCurve(['M', 'L', 'S']);
  assert.equal(store.isCustomCurveActive, false);
  assert.equal(localStorage.getItem('b2b_matrix_custom_curve'), null);
  store.incrementRow('black');
  assert.deepEqual({ ...store.cart }, { 10: 3, 11: 2, 12: 1 });
  store.applyMatrixData(payload());
  assert.equal(store.isCustomCurveActive, false);
  assert.deepEqual(store.activeCurveSizes, ['S', 'M', 'L']);
});
test('legacy full-size curves normalize regardless of order or duplicate sizes', () => {
  localStorage.setItem('b2b_matrix_custom_curve', JSON.stringify(['L', 'S', 'M', 'S']));
  store.initCustomCurve();
  assert.equal(store.isCustomCurveActive, false);
  assert.equal(localStorage.getItem('b2b_matrix_custom_curve'), null);
});
test('restoring incompatible curves clears the saved preference', () => {
  for (const sizes of [['42', '44'], ['S', 'M', 'L', 'XL']]) {
    const saved = JSON.stringify(sizes);
    localStorage.setItem('b2b_matrix_custom_curve', saved);
    store.initCustomCurve();
    assert.equal(store.customCurve, null);
    assert.equal(localStorage.getItem('b2b_matrix_custom_curve'), null);
  }
});
test('reopening a product reuses only catalog structure and fetches fresh prices and quantities', async () => {
  store.productId = 987321;
  store.applyMatrixData({ ...payload(), structure_version: 'fixture-version', product_name: 'Test product', parent_sku: 'TEST' });
  setActivePinia(createPinia());
  const reopened = useMatrixStore();
  reopened.apiEndpoint = '/wp-admin/admin-ajax.php';
  reopened.sessionEndpoint = '/wp-admin/admin-ajax.php';
  transport({ success: true, data: { user_id: 1, nonce: 'fresh', structure_version: 'fixture-version', prices: { 10: 20, 11: 21, 12: 22 }, existing_quantities: { 10: 4 }, currency: 'EUR' } });
  await reopened.loadProduct(987321);
  assert.match(calls[0].url, /structure_version=fixture-version/);
  assert.equal(reopened.data.matrix.black.S.price, 20);
  assert.equal(reopened.cart[10], 4);
  assert.equal(reopened.ready, true);
});
test('full-size selection still resets when browser storage is unavailable', () => {
  store.setCustomCurve(['M']);
  localStorage.removeItem = () => { throw new Error('Storage blocked'); };
  store.setCustomCurve(['L', 'M', 'S']);
  assert.equal(store.isCustomCurveActive, false);
  assert.deepEqual(store.activeCurveSizes, ['S', 'M', 'L']);
});
test('reject negative, decimal, excessive and unknown quantities', () => {
  for (const value of [-1, 1.5, 100000, Infinity, 'bad']) store.setQuantity(10, value);
  store.setQuantity(999, 5);
  assert.equal(store.cart[10], 2); assert.equal(store.cart[999], undefined);
});
test('zero quantities are transmitted; untouched quantities are not', async () => {
  transport({ success: true, data: { items: { 11: 1 } } });
  store.setQuantity(10, 0); await store.submitOrder();
  assert.deepEqual(JSON.parse(calls[1].options.body).items, [{ id: 10, qty: 0 }]);
  assert.equal(calls[1].options.headers['X-WP-Nonce'], 'fresh_nonce');
  assert.equal(calls[1].options.cache, 'no-store');
  assert.equal(store.isDirty, false); assert.equal(store.totalQty, 1);
});
test('list matrix save requests and retains its canonical parent patch in the same POST', async () => {
  const patch = { parentId: 100, item: { id: 100, sku: 'TEST', name: 'Product', total_qty: 4, total_val: 63, root_filters: [], category_filters: [] }, quoteVersion: 'a'.repeat(64) };
  store.includeListPatch = true;
  transport({ success: true, data: { items: { 10: 3, 11: 1 }, listPatch: patch } });
  store.setQuantity(10, 3); const saved = await store.submitOrder();
  assert.deepEqual(saved, { 10: 3, 11: 1 }); assert.deepEqual(store.savedListPatch, patch);
  assert.equal(JSON.parse(calls[1].options.body).include_list_patch, 1);
  assert.equal(calls.length, 2); assert.equal(store.isDirty, false);
});
test('list save rejects a missing or foreign summary and preserves editable quantities', async () => {
  store.includeListPatch = true;
  for (const patch of [undefined, { parentId: 999, item: null, quoteVersion: 'a'.repeat(64) }]) {
    transport({ success: true, data: { items: { 10: 3, 11: 1 }, listPatch: patch } });
    store.setQuantity(10, 3);
    assert.equal(await store.submitOrder(), undefined);
    assert.equal(store.isDirty, true); assert.equal(store.cart[10], 3); assert.equal(store.existingQuantities[10], 2);
  }
});
test('HTTP 200 success:false never reports a successful save or loses edits', async () => {
  transport({ success: false, data: { message: 'Database unavailable' } });
  store.setQuantity(10, 9); await store.submitOrder();
  assert.equal(store.feedbackStatus, 'error'); assert.equal(store.feedbackMsg, 'Database unavailable');
  assert.equal(store.cart[10], 9); assert.equal(store.existingQuantities[10], 2); assert.equal(store.isDirty, true);
});
test('arbitrary HTTP 200 response is not a save acknowledgement', async () => {
  transport({}); store.setQuantity(10, 9); await store.submitOrder();
  assert.equal(store.feedbackStatus, 'error'); assert.equal(store.isDirty, true);
});
test('server quantities become the canonical baseline; hidden items are preserved separately', async () => {
  transport({ success: true, data: { items: { 10: 4, 11: 1, 99: 8 } } });
  store.setQuantity(10, 5); await store.submitOrder();
  assert.equal(store.cart[10], 4); assert.equal(store.unavailableCount, 1);
  assert.equal(store.totalQty, 5); assert.equal(store.isDirty, false);
});
test('blocked save prevents duplicate requests and edits during the request', async () => {
  let release;
  globalThis.fetch = async () => { calls.push(1); return new Promise(resolve => { release = () => resolve(reply({ success: false, message: 'stop' })); }); };
  store.setQuantity(10, 5);
  const saving = store.submitOrder(); await store.submitOrder(); store.incrementRow('black');
  assert.equal(calls.length, 1); assert.equal(store.cart[10], 5);
  release(); await saving; assert.equal(store.isSubmitting, false);
});
test('changed login cannot save the previous account quantities into another account', async () => {
  store.userId = 2; transport({ success: true, data: { items: {} } });
  store.setQuantity(10, 4); await store.submitOrder();
  assert.equal(calls.length, 1); assert.equal(store.feedbackStatus, 'error'); assert.equal(store.isDirty, true);
});
test('plain permalink REST URL is recognized as REST, including subdirectory installations', async () => {
  store.apiEndpoint = '/store/index.php?rest_route=/b2b/v1/matrix';
  store.sessionEndpoint = '/store/wp-admin/admin-ajax.php';
  transport({ success: true, data: { items: { 10: 3, 11: 1 } } });
  store.setQuantity(10, 3); await store.submitOrder();
  assert.equal(calls[1].options.headers['Content-Type'], 'application/json');
  assert.ok(calls[0].url.includes('/store/wp-admin/admin-ajax.php'));
});
test('AJAX fallback includes action on reads and explicit acknowledgement on writes', async () => {
  store.apiEndpoint = '/wp-admin/admin-ajax.php';
  transport({ success: true, data: { ...payload(), nonce: 'fresh_nonce', user_id: 1 } }); await store.fetchMatrix();
  assert.equal(calls.length, 1);
  assert.ok(calls[0].url.includes('action=b2b_get_matrix_data'));
  assert.equal(store.ready, true);
  transport({ success: true, data: { items: { 10: 4, 11: 1 } } });
  store.setQuantity(10, 4); await store.submitOrder();
  assert.equal(calls[2].options.body.get('action'), 'b2b_save_matrix');
  assert.equal(store.isDirty, false);
});
test('failed load has a persistent actionable error and never substitutes demo data', async () => {
  transport({ code: 'rest_forbidden', message: 'Please sign in' }, 401);
  store.data = null; await store.fetchMatrix();
  assert.equal(store.data, null); assert.equal(store.loadError, 'Please sign in'); assert.equal(store.loading, false);
});
test('cross-origin endpoints are refused before sending credentials or nonce', async () => {
  store.sessionEndpoint = 'https://elsewhere.test/session';
  transport({}); await store.fetchMatrix(); assert.equal(calls.length, 0); assert.ok(store.loadError);
});
test('network and malformed JSON failures leave the saved baseline intact', async () => {
  globalThis.fetch = async () => { throw new TypeError('network down'); };
  store.setQuantity(10, 7); await store.submitOrder();
  assert.equal(store.cart[10], 7); assert.equal(store.existingQuantities[10], 2);
  globalThis.fetch = async () => ({ ok: true, json: async () => { throw new SyntaxError(); } });
  await assert.rejects(requestJSON('/test'), /μη έγκυρη απάντηση/);
});
test('invalid response quantities cannot replace the baseline', async () => {
  transport({ success: true, data: { items: { 10: -3 } } });
  store.setQuantity(10, 8); await store.submitOrder();
  assert.equal(store.feedbackStatus, 'error'); assert.equal(store.cart[10], 8); assert.equal(store.existingQuantities[10], 2);
});
test('pack curve multipliers increment according to custom ratio and skip zeroed sizes', () => {
  store.setCustomCurve({ S: 0, M: 2, L: 1 });
  assert.equal(store.isCustomSizesActive, true);
  assert.equal(store.isCustomStepActive, true);
  assert.equal(store.isCustomCurveActive, true);
  assert.deepEqual(store.activeCurveSizes, ['M', 'L']);
  assert.equal(store.packTotalUnits, 3);
  assert.equal(store.curveMultiplier('S'), 0);
  assert.equal(store.curveMultiplier('M'), 2);
  assert.equal(store.curveMultiplier('L'), 1);
  // Initial cart: 10(S)=2, 11(M)=1, 12(L)=0
  store.incrementRow('black', 1);
  assert.equal(store.cart[10], 2); // S had multiplier 0, unchanged
  assert.equal(store.cart[11], 3); // M increased by +2
  assert.equal(store.cart[12], 1); // L increased by +1
  store.incrementRow('black', -1);
  assert.equal(store.cart[10], 2); // S unchanged
  assert.equal(store.cart[11], 1); // M decreased by 2
  assert.equal(store.cart[12], 0); // L decreased by 1
});


test('cached structure never hydrates private quantities or prices from HTML', () => {
  const structure = { ...payload(), product_id: 100, structure_version: 'v1', nonce: 'old', user_id: 88 };
  store.initFromDOM({ dataset: { productId: '100', structure: JSON.stringify(structure) } });
  assert.equal(store.ready, false);
  assert.deepEqual(store.cart, {}); assert.deepEqual(store.existingQuantities, {});
  assert.equal(store.data.matrix.black.S.price, null);
  store.setQuantity(10, 99); assert.deepEqual(store.cart, {});
});
test('one uncached read hydrates current customer quantities over cached structure', async () => {
  store.applyStructurePreview({ ...payload(), structure_version: 'v1' });
  transport({ success: true, data: { nonce: 'fresh', user_id: 1, structure_version: 'v1', prices: { 10: 20, 11: 21, 12: 22 }, existing_quantities: { 10: 7 }, appearance: { lightbox: 'plugin' } } });
  await store.fetchMatrix();
  assert.equal(calls.length, 1); assert.ok(calls[0].url.includes('structure_version=v1'));
  assert.equal(calls[0].options.cache, 'no-store');
  assert.equal(store.ready, true); assert.equal(store.cart[10], 7);
  assert.equal(store.currentTotalPriceRaw, 140); assert.equal(store.lightboxMode, 'plugin');
});
test('changed catalog replaces stale structure before editing becomes available', async () => {
  store.applyStructurePreview({ ...payload(), structure_version: 'old' });
  const fresh = payload(); delete fresh.matrix.black.L; fresh.sizes = ['S', 'M'];
  transport({ success: true, data: { ...fresh, structure_version: 'new', nonce: 'fresh', user_id: 1 } });
  await store.fetchMatrix();
  assert.equal(store.ready, true); assert.deepEqual(store.data.sizes, ['S', 'M']);
  assert.equal(store.cart[12], undefined);
});
test('guest can preview quantities but cannot send a save request', async () => {
  store.userId = null;
  transport({ success: true, data: { ...payload(), existing_quantities: {}, nonce: 'guest', user_id: 0 } });
  await store.fetchMatrix(); assert.equal(store.ready, true); assert.equal(store.canSave, false);
  store.setQuantity(10, 4); assert.equal(store.totalQty, 4);
  await store.submitOrder(); assert.equal(calls.length, 1); assert.equal(store.feedbackStatus, 'error');
});
test('mismatched compact response cannot unlock cached cells', async () => {
  store.applyStructurePreview({ ...payload(), structure_version: 'old' });
  transport({ success: true, data: { nonce: 'fresh', user_id: 1, structure_version: 'new', prices: { 10: 10 } } });
  await store.fetchMatrix(); assert.equal(store.ready, false); assert.ok(store.loadError);
});
test('missing prices are not displayed as a misleading zero total', () => {
  const next = payload(); next.matrix.black.S.price = null; store.applyMatrixData(next);
  assert.equal(store.totalPrice, '—'); assert.equal(store.formatMoney(null), '—');
});
