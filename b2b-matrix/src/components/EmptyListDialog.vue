<template>
  <DialogModal :open="open" label="Άδειασμα λίστας" @close="requestClose">
    <section class="empty-guard" :class="'step-' + step">
      <header class="empty-guard-header">
        <span class="empty-guard-icon"><UiIcon name="trash" /></span>
        <div>
          <p class="empty-guard-step">Βήμα {{ step }} από 2</p>
          <h2>{{ step === 1 ? 'Άδειασμα λίστας' : 'Επιβεβαίωση διαγραφής' }}</h2>
        </div>
      </header>
      <ol class="empty-guard-progress" aria-hidden="true"><li class="done"></li><li :class="{ done: step === 2 }"></li></ol>

      <template v-if="step === 1">
        <p class="empty-guard-alert" role="alert"><UiIcon name="alert" /><span><strong>Η ενέργεια είναι οριστική και δεν αναιρείται.</strong> Θα διαγραφεί ολόκληρη η λίστα, ανεξάρτητα από τα φίλτρα προβολής.</span></p>
        <dl class="empty-guard-summary">
          <div><dt>Κωδικοί</dt><dd>{{ itemCount }}</dd></div>
          <div><dt>Τεμάχια</dt><dd>{{ totalQty }}</dd></div>
          <div><dt>Αξία</dt><dd>{{ totalValue }}</dd></div>
          <div v-if="hasNote" class="empty-guard-note"><dt>Σημείωση πελάτη</dt><dd>Θα διαγραφεί</dd></div>
        </dl>
        <footer class="empty-guard-actions">
          <B2BButton ref="backButton" variant="outline" @click="requestClose">Ακύρωση</B2BButton>
          <B2BButton variant="danger" icon="chevron-right" @click="goToConfirm">Συνέχεια</B2BButton>
        </footer>
      </template>

      <form v-else class="empty-guard-form" novalidate @submit.prevent="submit">
        <label :for="inputId">Για επιβεβαίωση πληκτρολογήστε <kbd>{{ word }}</kbd> και πατήστε <kbd>Enter</kbd></label>
        <input
          :id="inputId"
          ref="confirmInput"
          v-model="typed"
          class="b2b-field empty-guard-input"
          :class="{ 'is-match': state === 'match', 'is-mismatch': state === 'mismatch' || attempted && state !== 'match' }"
          type="text"
          inputmode="text"
          enterkeyhint="done"
          autocomplete="off"
          autocapitalize="none"
          autocorrect="off"
          spellcheck="false"
          :placeholder="word"
          :disabled="busy"
          :aria-describedby="inputId + '-hint'"
          :aria-invalid="state === 'mismatch' || (attempted && state !== 'match')"
          @paste.prevent="blocked = true"
          @drop.prevent="blocked = true"
          @input="blocked = false; attempted = false"
          @keydown.enter.prevent="submit"
        />
        <p :id="inputId + '-hint'" class="empty-guard-hint" :class="hintClass" role="status" aria-live="polite">
          <UiIcon v-if="state === 'match'" name="check-circle" />
          <UiIcon v-else-if="hintClass === 'is-error'" name="alert" />
          <span>{{ hint }}</span>
        </p>
        <footer class="empty-guard-actions">
          <B2BButton variant="outline" :disabled="busy" icon="chevron-left" @click="backToSummary">Πίσω</B2BButton>
          <B2BButton type="submit" variant="danger" icon="trash" :disabled="state !== 'match'" :loading="busy">{{ busy ? 'Διαγραφή…' : 'Οριστικό άδειασμα' }}</B2BButton>
        </footer>
      </form>
    </section>
  </DialogModal>
</template>

<script setup>
import { ref, computed, watch, nextTick, useId } from 'vue';
import DialogModal from './DialogModal.vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import { EMPTY_CONFIRM_WORD, confirmProgress, needsLatinKeyboard } from '../emptyConfirm.js';

const props = defineProps({
  open: Boolean,
  busy: Boolean,
  itemCount: { type: Number, default: 0 },
  totalQty: { type: Number, default: 0 },
  totalValue: { type: String, default: '—' },
  hasNote: Boolean,
});
const emit = defineEmits(['close', 'confirm']);
const word = EMPTY_CONFIRM_WORD;
const inputId = useId();
const step = ref(1), typed = ref(''), blocked = ref(false), attempted = ref(false);
const confirmInput = ref(null), backButton = ref(null);
const state = computed(() => confirmProgress(typed.value));
const latin = computed(() => needsLatinKeyboard(typed.value));
const hintClass = computed(() => state.value === 'match' ? 'is-ok' : (blocked.value || latin.value || state.value === 'mismatch' || attempted.value) ? 'is-error' : '');
const hint = computed(() => {
  if (state.value === 'match') return 'Πατήστε Enter ή «Οριστικό άδειασμα» για να διαγραφεί η λίστα.';
  if (blocked.value) return 'Η επικόλληση δεν επιτρέπεται. Πληκτρολογήστε τη λέξη.';
  if (latin.value) return 'Το πληκτρολόγιο είναι σε ελληνικά. Αλλάξτε σε λατινικούς χαρακτήρες (EN).';
  if (state.value === 'mismatch' || attempted.value) return `Η λέξη δεν ταιριάζει. Πληκτρολογήστε ακριβώς «${word}».`;
  return 'Το κουμπί ενεργοποιείται μόλις πληκτρολογήσετε σωστά τη λέξη.';
});

function reset() { step.value = 1; typed.value = ''; blocked.value = false; attempted.value = false; }
watch(() => props.open, open => { if (open) { reset(); nextTick(() => backButton.value?.$el?.focus()); } });
function requestClose() { if (!props.busy) emit('close'); }
async function goToConfirm() { step.value = 2; await nextTick(); confirmInput.value?.focus(); }
async function backToSummary() { reset(); await nextTick(); backButton.value?.$el?.focus(); }
function submit(event) {
  if (props.busy || event?.isComposing) return;
  if (state.value !== 'match') { attempted.value = true; confirmInput.value?.focus(); return; }
  emit('confirm');
}
</script>

<style scoped>
.empty-guard { display: grid; gap: 18px; color: #0f172a; }
.empty-guard-header { display: flex; align-items: center; gap: 14px; }
.empty-guard-header h2 { margin: 2px 0 0; font-size: 19px; font-weight: 700; line-height: 1.3; letter-spacing: -.2px; }
.empty-guard-icon { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border-radius: var(--b2b-radius, 10px); background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.empty-guard-icon :deep(svg) { width: 21px; height: 21px; }
.empty-guard-step { margin: 0; color: #64748b; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.empty-guard-progress { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin: -6px 0 0; padding: 0; list-style: none; }
.empty-guard-progress li { height: 4px; border-radius: 4px; background: #e2e8f0; transition: background-color .2s ease; }
.empty-guard-progress li.done { background: #dc2626; }
.empty-guard-alert { display: flex; gap: 10px; margin: 0; padding: 12px 14px; border: 1px solid #fecaca; border-radius: var(--b2b-radius, 8px); background: #fef2f2; color: #991b1b; font-size: 13px; line-height: 1.55; }
.empty-guard-alert :deep(svg) { flex: 0 0 18px; margin-top: 1px; }
.empty-guard-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; margin: 0; }
.empty-guard-summary > div { min-width: 0; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: var(--b2b-radius, 8px); background: #f8fafc; }
.empty-guard-summary dt { color: #64748b; font-size: 11px; font-weight: 600; }
.empty-guard-summary dd { margin: 3px 0 0; font-size: 17px; font-weight: 700; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
.empty-guard-summary .empty-guard-note { grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.empty-guard-summary .empty-guard-note dd { margin: 0; color: #b91c1c; font-size: 13px; }
.empty-guard-form { display: grid; gap: 10px; margin: 0; }
.empty-guard-form label { color: #334155; font-size: 14px; line-height: 1.6; }
kbd { display: inline-block; padding: 1px 7px; border: 1px solid #cbd5e1; border-bottom-width: 2px; border-radius: 5px; background: #f8fafc; color: #0f172a; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; font-weight: 700; line-height: 1.5; }
.empty-guard-input { box-sizing: border-box; width: 100%; min-height: 50px; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: var(--b2b-radius-input, 8px); background: #fff; color: #0f172a; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 18px; letter-spacing: .04em; transition: border-color .15s ease, box-shadow .15s ease; }
.empty-guard-input::placeholder { color: #cbd5e1; }
.empty-guard-input:focus { outline: none; border-color: #dc2626; box-shadow: 0 0 0 3px #fee2e2; }
.empty-guard-input.is-mismatch { border-color: #dc2626; }
.empty-guard-input.is-match, .empty-guard-input.is-match:focus { border-color: #16a34a; box-shadow: 0 0 0 3px #dcfce7; }
.empty-guard-hint { display: flex; align-items: flex-start; gap: 6px; min-height: 20px; margin: 0; color: #64748b; font-size: 12px; line-height: 1.5; }
.empty-guard-hint :deep(svg) { flex: 0 0 16px; width: 16px; height: 16px; margin-top: 1px; }
.empty-guard-hint.is-ok { color: #15803d; font-weight: 600; }
.empty-guard-hint.is-error { color: #b91c1c; font-weight: 600; }
.empty-guard-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; padding-top: 4px; }
@media (max-width: 480px) {
  .empty-guard-summary { grid-template-columns: 1fr 1fr; }
  .empty-guard-actions > * { flex: 1 1 140px; }
}
</style>
