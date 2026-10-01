import test from 'node:test';
import assert from 'node:assert/strict';
import { createListApi } from '../src/listApi.js';
const origin = 'https://shop.test';
const ok = data => new Response(JSON.stringify({ success: true, data }));
test('list bootstrap is uncached and every write gets a fresh nonce with expected account', async () => {
 const calls = [];
 const api = createListApi('/sub/wp-admin/admin-ajax.php', async (url, options) => {
  calls.push({ url, options });
  return ok(calls.length === 1 ? { user_id: 7 } : calls.length === 2 ? { user_id: 7, nonce: 'fresh' } : true);
 }, origin);
 await api.read('list-data'); await api.write('empty');
 assert.ok(calls.every(c => c.options.cache === 'no-store' && c.options.credentials === 'same-origin'));
 assert.equal(calls[2].options.body.get('security'), 'fresh');
 assert.equal(calls[2].options.body.get('expected_user_id'), '7');
 assert.equal(calls[2].options.body.get('action'), 'b2b_empty_custom_list');
});
test('account switch rejects a write before sending any mutation', async () => {
 let calls = 0;
 const api = createListApi('/ajax', async () => ok(++calls === 1 ? { user_id: 7 } : { user_id: 8, nonce: 'new' }), origin);
 await api.read('list-data'); await assert.rejects(api.write('empty'), { code: 'account_changed' }); assert.equal(calls, 2);
});
test('uncertain POST is never automatically replayed through a fallback', async () => {
 let calls = 0;
 const api = createListApi('/ajax', async () => { calls++; if (calls === 3) throw new Error('connection lost'); return ok({ user_id: 7, nonce: 'fresh' }); }, origin);
 await api.read('list-data'); await assert.rejects(api.write('start-order'), /connection lost/); assert.equal(calls, 3);
});
test('HTTP 200 failure and malformed responses never acknowledge a write', async () => {
 for (const response of [new Response('{'), new Response(JSON.stringify({ success: false, data: { message: 'denied' } }))]) {
  const api = createListApi('/ajax', async () => response, origin); await assert.rejects(api.read('list-data'));
 }
});
test('foreign endpoint is rejected before credentials are sent', () => {
 assert.throws(() => createListApi('https://other.test/ajax', () => { throw new Error('must not fetch'); }, origin));
});
