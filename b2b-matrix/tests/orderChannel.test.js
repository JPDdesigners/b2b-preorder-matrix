import test from 'node:test';
import assert from 'node:assert/strict';
import { orderChannelSelection } from '../src/orderChannel.js';
const payload = (canChoose = true, defaultLive = false, user_id = 7) => ({ user_id, orderChannelContext: { canChoose, defaultLive } });
test('customer context hides control and discards stale live choice', () => {
 assert.deepEqual(orderChannelSelection(payload(false), { userId: 7, canChoose: true, live: true }), { userId: 7, canChoose: false, live: false });
 assert.equal(orderChannelSelection({ user_id: 7 }).live, false);
});
test('direct manager defaults off and switched seller defaults on', () => {
 assert.equal(orderChannelSelection(payload()).live, false);
 assert.equal(orderChannelSelection(payload(true, true)).live, true);
});
test('list refresh preserves manual choice for the same account', () => {
 assert.equal(orderChannelSelection(payload(true, true), { userId: 7, canChoose: true, live: false }).live, false);
 assert.equal(orderChannelSelection(payload(), { userId: 7, canChoose: true, live: true }).live, true);
});
test('account or permission change resets to server default', () => {
 assert.equal(orderChannelSelection(payload(true, false, 8), { userId: 7, canChoose: true, live: true }).live, false);
 assert.equal(orderChannelSelection(payload(true, true), { userId: 7, canChoose: false, live: false }).live, true);
});
test('batch snapshot overrides later checkbox changes, including old unknown batches', () => {
 const previous = { userId: 7, canChoose: true, live: false };
 assert.equal(orderChannelSelection({ ...payload(), activeBatch: { order_channel: 'live' } }, previous).live, true);
 assert.equal(orderChannelSelection({ ...payload(true, true), activeBatch: { order_channel: 'online' } }, previous).live, false);
 assert.equal(orderChannelSelection({ ...payload(true, true), activeBatch: {} }, previous).live, false);
});
