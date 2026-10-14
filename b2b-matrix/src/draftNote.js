// Account draft only: no browser storage, polling, or automatic conflict overwrite.
export function createDraftNote({ read, write, publish, delay = 800, schedule = setTimeout, cancel = clearTimeout }) {
  let state = { note: '', revision: '', dirty: false, status: 'loading', error: '' };
  let baseline = '', timer, pending, generation = 0, readVersion = 0;
  const emit = () => publish({ ...state });
  const stopTimer = () => { cancel(timer); timer = undefined; };
  const later = () => { stopTimer(); timer = schedule(() => { timer = undefined; save().catch(() => {}); }, delay); };
  const conflict = draft => { stopTimer(); state.status = 'conflict'; state.remoteNote = draft?.note ?? null; state.error = 'Το σχόλιο άλλαξε από άλλη καρτέλα ή συσκευή. Επιλέξτε ποιο θα κρατήσετε.'; emit(); };
  function hydrate(draft) {
    if (!draft?.revision || typeof draft.note !== 'string' || pending) return;
    if (state.dirty || state.status === 'conflict') {
      if (state.revision && draft.revision !== state.revision) conflict(draft);
      return;
    }
    baseline = draft.note; state = { note: draft.note, revision: draft.revision, dirty: false, status: 'saved', error: '' }; emit();
  }
  function edit(note) {
    state.note = note; state.dirty = note !== baseline;
    if (state.status !== 'conflict') {
      state.error = ''; state.status = pending ? 'saving' : state.dirty ? 'dirty' : 'saved';
      stopTimer(); if (state.dirty) later();
    }
    emit();
  }
  async function save() {
    stopTimer();
    if (pending) return pending;
    if (state.status === 'conflict') throw new Error(state.error);
    if (!state.revision) throw new Error('Δεν έχει φορτωθεί το αποθηκευμένο σχόλιο.');
    if (!state.dirty) return state.revision;
    const note = state.note, revision = state.revision, session = generation;
    ++readVersion; state.status = 'saving'; state.error = ''; emit();
    pending = (async () => {
      try {
        const draft = await write({ customer_note: note, note_revision: revision });
        if (session !== generation) throw new Error('Η σελίδα άλλαξε.');
        if (!draft?.revision || typeof draft.note !== 'string') throw new Error('Η αποθήκευση του σχολίου δεν επιβεβαιώθηκε.');
        baseline = draft.note; state.revision = draft.revision;
        if (state.note === note) state.note = draft.note;
        state.dirty = state.note !== baseline; state.status = state.dirty ? 'dirty' : 'saved'; state.error = ''; emit();
        if (state.dirty) later();
        return state.revision;
      } catch (error) {
        if (session === generation) {
          if (error.code === 'note_conflict') {
            conflict();
            try { const remote = await read(); if (session === generation && state.status === 'conflict') { state.remoteNote = remote.note; emit(); } } catch { /* local text stays available even if the preview cannot load */ }
          }
          else { stopTimer(); state.status = 'error'; state.error = error.message; emit(); }
        }
        throw error;
      } finally { if (session === generation) pending = undefined; }
    })();
    return pending;
  }
  async function flush() {
    do { await save(); } while (state.dirty);
    return state.revision;
  }
  async function refresh() {
    if (pending || !state.revision) return;
    const version = ++readVersion, session = generation;
    const draft = await read();
    if (version === readVersion && session === generation) hydrate(draft);
  }
  async function resolve(keepMine) {
    const session = generation;
    if (pending) { try { await pending; } catch { /* resolve the conflict already shown by that save */ } }
    if (session !== generation) return;
    const draft = await read();
    if (session !== generation) return;
    if (!draft?.revision || typeof draft.note !== 'string') throw new Error('Δεν έχει φορτωθεί το αποθηκευμένο σχόλιο.');
    ++readVersion; stopTimer();
    const note = keepMine ? state.note : draft.note;
    baseline = draft.note;
    state = { note, revision: draft.revision, dirty: note !== baseline, status: note !== baseline ? 'dirty' : 'saved', error: '' }; emit();
    if (state.dirty) await flush();
  }
  function reset() { stopTimer(); ++generation; ++readVersion; pending = undefined; baseline = ''; state = { note: '', revision: '', dirty: false, status: 'loading', error: '' }; emit(); }
  async function settle() { stopTimer(); if (pending) { try { await pending; } catch { /* explicit clear may discard an unsaved draft */ } } stopTimer(); ++readVersion; }
  return { edit, hydrate, flush, refresh, resolve, settle, markConflict: conflict, reset, dispose: reset };
}
