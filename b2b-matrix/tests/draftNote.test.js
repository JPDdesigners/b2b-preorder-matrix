import test from 'node:test';
import assert from 'node:assert/strict';
import { createDraftNote } from '../src/draftNote.js';

const deferred = () => { let resolve, reject; const promise = new Promise((a,b) => { resolve=a; reject=b; }); return { promise, resolve, reject }; };
function fixture(overrides = {}) {
 let state, revision = 1, server = { note: 'Αρχικό', revision: 'r1' }, timer, calls = [];
 const draft = createDraftNote({ read: async () => ({...server}), write: async fields => { calls.push(fields); server = { note: fields.customer_note.trim(), revision: `r${++revision}` }; return {...server}; }, publish: value => { state = value; }, schedule: fn => { timer = fn; return fn; }, cancel: () => { timer = undefined; }, ...overrides });
 draft.hydrate(server);
 return { draft, calls, state: () => state, setServer: value => { server = value; }, tick: async () => { const fn = timer; timer = undefined; fn?.(); await new Promise(r => setImmediate(r)); } };
}
test('debounce writes only the latest note and publishes durable saved status', async () => {
 const f = fixture(); f.draft.edit('Α'); f.draft.edit('Από σπίτι');
 assert.equal(f.calls.length,0); await f.tick();
 assert.equal(f.calls.length,1); assert.deepEqual(f.calls[0],{customer_note:'Από σπίτι',note_revision:'r1'});
 assert.equal(f.state().status,'saved'); assert.equal(f.state().dirty,false);
});
test('flush before submission waits for serialized edits made during an in-flight save', async () => {
 const gate=deferred(), calls=[]; const f = fixture({write: async fields => { calls.push(fields); if(calls.length===1) return gate.promise; return {note:fields.customer_note,revision:'r3'}; }});
 f.draft.edit('Πρώτο'); const saving=f.draft.flush(); await Promise.resolve(); f.draft.edit('Δεύτερο');
 assert.equal(calls.length,1); gate.resolve({note:'Πρώτο',revision:'r2'});
 assert.equal(await saving,'r3'); assert.equal(calls.length,2); assert.equal(calls[1].note_revision,'r2'); assert.equal(f.state().note,'Δεύτερο');
});
test('clean focus refresh loads a server note without fetching the product list', async () => {
 const f=fixture(); f.setServer({note:'Άλλη συσκευή',revision:'r2'}); await f.draft.refresh();
 assert.equal(f.state().note,'Άλλη συσκευή'); assert.equal(f.calls.length,0);
});
test('dirty focus refresh preserves local text and requires explicit conflict resolution', async () => {
 const f=fixture(); f.draft.edit('Δικό μου'); f.setServer({note:'Νεότερο',revision:'r2'}); await f.draft.refresh();
 assert.equal(f.state().status,'conflict'); assert.equal(f.state().note,'Δικό μου'); await f.tick(); assert.equal(f.calls.length,0);
 await assert.rejects(f.draft.flush()); await f.draft.resolve(false);
 assert.equal(f.state().note,'Νεότερο'); assert.equal(f.state().dirty,false);
});
test('keeping local text reads the latest revision before an explicit replacement', async () => {
 const f=fixture(); f.draft.edit('Δικό μου'); f.setServer({note:'Νεότερο',revision:'r2'}); f.draft.markConflict(); await f.draft.resolve(true);
 assert.equal(f.calls[0].note_revision,'r2'); assert.equal(f.calls[0].customer_note,'Δικό μου'); assert.equal(f.state().status,'saved');
});
test('a rejected conflict or uncertain response never auto-replays a write', async () => {
 for(const code of ['note_conflict','connection_lost']) {
  let calls=0; const f=fixture({write: async()=>{ calls++; throw Object.assign(new Error('Αποτυχία'),{code}); }});
  f.draft.edit('Νέο'); await assert.rejects(f.draft.flush()); await f.tick();
  assert.equal(calls,1); assert.equal(f.state().dirty,true); assert.equal(f.state().note,'Νέο'); assert.equal(f.state().status,code==='note_conflict'?'conflict':'error');
 }
});
test('an older focus response cannot replace a note saved after that read began', async () => {
 const gate=deferred(); const f=fixture({read:()=>gate.promise});
 const refresh=f.draft.refresh(); f.draft.edit('Νέο'); await f.draft.flush(); gate.resolve({note:'Παλιό',revision:'r1'}); await refresh;
 assert.equal(f.state().note,'Νέο'); assert.equal(f.state().status,'saved');
});
test('reset after clear or account change ignores late responses and cancels autosave', async () => {
 const gate=deferred(); const f=fixture({write:()=>gate.promise}); f.draft.edit('Ιδιωτικό'); const pending=f.draft.flush();
 f.draft.reset(); f.draft.hydrate({note:'',revision:'next'}); gate.resolve({note:'Ιδιωτικό',revision:'old'}); await assert.rejects(pending);
 assert.equal(f.state().note,''); assert.equal(f.state().revision,'next'); await f.tick(); assert.equal(f.state().status,'saved');
});
test('explicit empty can discard a conflicting draft without saving it', async () => {
 const f=fixture(); f.draft.edit('Να διαγραφεί'); f.draft.markConflict(); await f.draft.settle(); f.draft.reset();
 await f.tick(); assert.equal(f.calls.length,0); assert.equal(f.state().note,'');
});
test('reverting while a save is pending remains visibly saving and flush restores that intent', async () => {
 const gate=deferred(), calls=[]; const f=fixture({write:async fields=>{calls.push(fields); return calls.length===1?gate.promise:{note:fields.customer_note,revision:'r3'};}});
 f.draft.edit('Πρώτο'); const saving=f.draft.flush(); f.draft.edit('Αρχικό');
 assert.equal(f.state().status,'saving'); gate.resolve({note:'Πρώτο',revision:'r2'}); await saving;
 assert.equal(f.state().note,'Αρχικό'); assert.equal(f.state().status,'saved'); assert.equal(calls[1].customer_note,'Αρχικό');
});
test('conflict resolution can be clicked while the failed save is still settling', async () => {
 const gate=deferred(); const f=fixture({write:()=>gate.promise});
 f.draft.edit('Δικό μου'); const saving=f.draft.flush().catch(()=>{}); f.draft.markConflict();
 const resolving=f.draft.resolve(false); gate.reject(Object.assign(new Error('Conflict'),{code:'note_conflict'}));
 await saving; await resolving; assert.equal(f.state().note,'Αρχικό'); assert.equal(f.state().status,'saved');
});
