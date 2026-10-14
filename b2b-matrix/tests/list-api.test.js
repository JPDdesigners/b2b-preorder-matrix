import test from 'node:test';
import assert from 'node:assert/strict';
import { createListApi } from '../src/listApi.js';
const origin = 'https://shop.test';
const ok = data => new Response(JSON.stringify({ success: true, data }));

test('draft reads cannot silently switch accounts and note writes carry revision, fresh nonce and only note text', async () => {
 const calls=[];
 const api=createListApi('/ajax', async (url,options) => {
  const fields=options.body || url.searchParams; calls.push({fields,options});
  return ok(fields.get('action')==='b2b_matrix_session' ? {user_id:7,nonce:'new'} : fields.get('action')==='b2b_get_preorder_note' ? {user_id:8,draft:{note:'Private',revision:'r2'}} : {user_id:7,note:'Text',revision:'r2'});
 },origin);
 await api.read('list-data'); await api.write('save-note',{customer_note:'Text',note_revision:'r1'});
 const sent=calls[2].fields;
 assert.equal(sent.get('action'),'b2b_save_preorder_note'); assert.equal(sent.get('security'),'new'); assert.equal(sent.get('note_revision'),'r1'); assert.equal(sent.get('customer_note'),'Text'); assert.equal(sent.get('expected_user_id'),'7'); assert.equal(sent.has('items'),false);
 await assert.rejects(api.read('draft-note'),{code:'account_changed'});
});
test('list reload rejects a changed account before hydrating its private draft', async () => {
 let uid=7;
 const api=createListApi('/ajax', async()=>ok({user_id:uid}),origin);
 await api.read('list-data'); uid=8;
 await assert.rejects(api.read('list-data'),{code:'account_changed'});
});
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

test('origin choice is included only with authenticated start-order and no client seller id', async () => {
 const calls = [];
 const api = createListApi('/ajax', async (url, options) => { calls.push({ url, options }); return ok({ user_id: 7, nonce: 'fresh' }); }, origin);
 await api.read('list-data'); await api.write('start-order', { live_showroom: '1' });
 const body = calls[2].options.body;
 assert.equal(body.get('action'), 'b2b_start_order'); assert.equal(body.get('live_showroom'), '1');
 assert.equal(body.get('expected_user_id'), '7'); assert.equal(body.get('security'), 'fresh');
 assert.equal(body.has('actor_id'), false);
});

test('cancellation acknowledgement sends its notice token with fresh nonce and account identity', async () => {
 const calls = [];
 const api = createListApi('/ajax', async (url, options) => {
  calls.push({ url, options }); return ok({ user_id: 7, nonce: 'fresh' });
 }, origin);
 await api.read('list-data'); await api.write('acknowledge-removals', { notice_token: 'notice-2' });
 const body = calls[2].options.body;
 assert.equal(calls[2].options.method, 'POST');
 assert.equal(body.get('action'), 'b2b_acknowledge_removed_items');
 assert.equal(body.get('notice_token'), 'notice-2');
 assert.equal(body.get('security'), 'fresh');
 assert.equal(body.get('expected_user_id'), '7');
});
