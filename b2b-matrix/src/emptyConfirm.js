// Type-to-confirm guard for destructive list actions (Vercel-style).
// The server checks the same word, so an old or scripted client cannot skip it.
export const EMPTY_CONFIRM_WORD = 'delete';

const GREEK = /[\u0370-\u03ff\u1f00-\u1fff]/;

export function normalizeConfirmInput(value) {
  return typeof value === 'string' ? value.trim().toLowerCase() : '';
}

export function matchesConfirmWord(value, word = EMPTY_CONFIRM_WORD) {
  return normalizeConfirmInput(value) === word;
}

// Typing "delete" on a Greek keyboard layout produces "δελετε"; tell the user to switch layout.
export function needsLatinKeyboard(value) {
  return typeof value === 'string' && GREEK.test(value);
}

export function confirmProgress(value, word = EMPTY_CONFIRM_WORD) {
  const typed = normalizeConfirmInput(value);
  if (!typed) return 'empty';
  if (typed === word) return 'match';
  return word.startsWith(typed) ? 'partial' : 'mismatch';
}
