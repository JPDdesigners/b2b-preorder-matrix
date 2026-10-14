<template>
  <aside class="list-sidebar" aria-label="Στοιχεία και σύνοψη προπαραγγελίας">
    <B2BCard class="sidebar-card">
      <section class="sidebar-section customer-section" aria-label="Στοιχεία πελάτη">
        <div class="section-heading">
          <span class="section-icon">
            <UiIcon name="user" />
          </span>
          <h2>Στοιχεία πελάτη</h2>
        </div>

        <dl class="customer-details">
          <div class="customer-detail-full">
            <dt>Επωνυμία</dt>
            <dd>
              <span v-if="loading" class="inline-skeleton wide"></span>
              <strong v-else>{{ customerInfo?.company || '—' }}</strong>
            </dd>
          </div>
          <div>
            <dt>ΑΦΜ</dt>
            <dd>
              <span v-if="loading" class="inline-skeleton"></span>
              <strong v-else>{{ customerInfo?.afm || '—' }}</strong>
            </dd>
          </div>
          <div>
            <dt>Email τιμολόγησης</dt>
            <dd>
              <span v-if="loading" class="inline-skeleton wide"></span>
              <strong v-else class="customer-email">{{ customerInfo?.email || '—' }}</strong>
            </dd>
          </div>
        </dl>
      </section>

      <section class="sidebar-section note-section" aria-label="Σημείωση πελάτη">
        <div class="section-heading">
          <span class="section-icon">
            <UiIcon name="edit" />
          </span>
          <h2>
            <label :for="noteId">Σημείωση πελάτη</label>
          </h2>
        </div>

        <textarea
          :id="noteId"
          :value="note"
          class="b2b-field note-textarea"
          rows="3"
          maxlength="4000"
          :disabled="locked || noteState.status === 'loading'"
          :aria-describedby="noteId + '-status'"
          placeholder="Κάτι που θέλετε να γνωρίζουμε…"
          @input="$emit('update:note', $event.target.value)"
          @blur="$emit('saveNote')"
        ></textarea>

        <p
          :id="noteId + '-status'"
          class="note-save-status"
          :class="{ 'note-save-error': ['error', 'conflict'].includes(noteState.status) }"
          role="status"
          aria-live="polite"
        >
          {{ noteStatusText }}
        </p>

        <p
          v-if="noteState.status === 'conflict' && noteState.remoteNote !== null"
          class="note-remote-preview"
        >
          <strong>Αποθηκευμένο σχόλιο:</strong><br />
          {{ noteState.remoteNote || '(Κενό σχόλιο)' }}
        </p>

        <div v-if="noteState.status === 'conflict'" class="note-conflict-actions">
          <B2BButton
            size="sm"
            variant="outline"
            :disabled="locked"
            @click="$emit('resolveNote', false)"
          >
            Φόρτωση αποθηκευμένου
          </B2BButton>
          <B2BButton
            size="sm"
            variant="outline"
            :disabled="locked"
            @click="$emit('resolveNote', true)"
          >
            Κράτημα δικού μου
          </B2BButton>
        </div>

        <B2BButton
          v-else-if="noteState.status === 'error'"
          size="sm"
          variant="outline"
          :disabled="locked"
          @click="$emit('saveNote')"
        >
          Δοκιμή ξανά
        </B2BButton>
      </section>

      <section
        v-if="channelSelection?.canChoose"
        class="sidebar-section order-channel-section"
        aria-label="Προέλευση παραγγελίας"
      >
        <label class="order-channel-toggle">
          <input
            v-model="channelSelection.live"
            type="checkbox"
            :disabled="locked"
          />
          <span>Παραγγελία από live δειγματισμό</span>
        </label>
        <p v-if="activeBatch">
          Η προέλευση έχει αποθηκευτεί στην καταχώριση{{
            activeBatch.order_channel === 'unknown' ? ' ως μη καταγεγραμμένη' : ''
          }}.
        </p>
        <p v-else>
          Επιλέξτε όταν καταχωρίζετε την παραγγελία μαζί με τον πελάτη στον δειγματισμό.
        </p>
      </section>

      <section class="sidebar-section summary-section" aria-label="Σύνοψη λίστας">
        <div class="section-heading">
          <span class="section-icon">
            <UiIcon name="clipboard-check" />
          </span>
          <h2>Σύνοψη λίστας</h2>
        </div>

        <div class="summary-total">
          <div class="summary-value">
            <span>Γενικό σύνολο</span>
            <strong v-if="loading" class="inline-skeleton wide"></strong>
            <strong v-else>{{ hasUnpriced ? '—' : money(totalVal || 0) }}</strong>
            <dl class="summary-rows">
              <div>
                <dt>Κωδικοί</dt>
                <dd>
                  <strong v-if="loading" class="inline-skeleton"></strong>
                  <strong v-else>{{ itemsCount }}</strong>
                </dd>
              </div>
              <div>
                <dt>Τεμάχια</dt>
                <dd>
                  <strong v-if="loading" class="inline-skeleton"></strong>
                  <strong v-else>{{ totalQty || 0 }}</strong>
                </dd>
              </div>
            </dl>
          </div>

          <B2BButton
            class="submit-list"
            icon="send"
            size="lg"
            :disabled="locked || !itemsCount || hasInvalid"
            @click="$emit('submit')"
          >
            Υποβολή λίστας
          </B2BButton>
        </div>
      </section>
    </B2BCard>
  </aside>
</template>

<script setup>
import UiIcon from './UiIcon.vue';
import B2BCard from './ui/B2BCard.vue';
import B2BButton from './ui/B2BButton.vue';

defineProps({
  customerInfo: { type: Object, default: () => ({}) },
  itemsCount: { type: Number, default: 0 },
  totalQty: { type: Number, default: 0 },
  totalVal: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
  hasUnpriced: { type: Boolean, default: false },
  hasInvalid: { type: Boolean, default: false },
  note: { type: String, default: '' },
  noteId: { type: String, required: true },
  noteState: { type: Object, required: true },
  noteStatusText: { type: String, default: '' },
  channelSelection: { type: Object, default: null },
  activeBatch: { type: Object, default: null },
  money: { type: Function, required: true },
});

defineEmits(['update:note', 'saveNote', 'resolveNote', 'submit']);
</script>

<style scoped>
.list-sidebar {
  min-width: 0;
}

.sidebar-card {
  display: grid;
  border-color: #e2e8f0 !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 16px -4px rgba(15, 23, 42, 0.05);
}

.sidebar-section {
  min-width: 0;
  padding: 16px;
}

.sidebar-section + .sidebar-section {
  border-top: 1px solid #f1f5f9;
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  margin-bottom: 12px;
}

.section-icon {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius-sm, 6px);
}

.section-icon :deep(svg) {
  width: 14px;
  height: 14px;
}

.section-heading h2,
.section-heading h2 label {
  margin: 0 !important;
  color: #0f172a;
  font-size: 13px;
  line-height: 1.3;
  font-weight: 700;
  cursor: pointer;
}

.customer-details {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px 12px;
  margin: 0 !important;
  padding: 10px 12px;
  border: 1px solid #f1f5f9;
  border-radius: var(--b2b-radius, 8px);
  background: #f8fafc;
}

.customer-detail-full {
  grid-column: 1 / -1;
  padding-bottom: 8px;
  border-bottom: 1px solid #e2e8f0;
}

.customer-details > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.customer-details dt {
  color: #64748b;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.customer-details dd,
.summary-rows dd {
  margin: 0 !important;
}

.customer-details strong {
  color: #0f172a;
  font-size: 12px;
  font-weight: 650;
  overflow-wrap: anywhere;
}

.customer-email {
  font-weight: 500 !important;
}

.note-textarea {
  min-height: 76px !important;
  padding: 8px 10px !important;
  font-size: 12.5px !important;
  resize: vertical;
  margin-bottom: 0 !important;
  background: #f8fafc !important;
  border-color: #cbd5e1 !important;
}

.note-textarea:focus {
  background: #ffffff !important;
}

.note-save-status {
  color: #64748b;
  font-size: 11px;
  line-height: 1.4;
  margin: 6px 0 0 !important;
}

.note-save-error {
  color: #b91c1c;
  font-weight: 600;
}

.note-conflict-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 8px;
}

.note-remote-preview {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  max-height: 120px;
  overflow: auto;
  padding: 10px;
  margin: 8px 0 !important;
  background: #fafafa;
  border: 1px solid #e4e4e7;
  border-radius: var(--b2b-radius-sm, 6px);
  font-size: 12px;
  line-height: 1.5;
}

.order-channel-toggle {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 38px;
  cursor: pointer;
  color: #0f172a;
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1.4;
}

.order-channel-toggle input {
  flex: 0 0 18px;
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--b2b-focus-ring, var(--b2b-primary));
}

.order-channel-toggle:has(input:disabled) {
  cursor: default;
  opacity: 0.7;
}

.order-channel-section p {
  margin: 6px 0 0 !important;
  color: #64748b;
  font-size: 11.5px;
  line-height: 1.5;
}

.summary-total {
  min-width: 0;
}

.summary-value {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: 0;
  padding: 16px 14px;
  border-radius: var(--b2b-radius, 8px);
  border-bottom: 3px solid var(--b2b-primary);
  background: #0f172a;
  text-align: center;
}

.summary-value > span {
  color: #94a3b8;
  font-size: 10.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.summary-value > strong {
  margin-top: 4px;
  color: #ffffff;
  font-size: clamp(22px, 2vw, 26px);
  line-height: 1.2;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.summary-rows {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  width: 100%;
  margin: 12px 0 0 !important;
  padding-top: 10px;
  border-top: 1px solid rgba(255, 255, 255, 0.14);
}

.summary-rows > div {
  display: flex;
  justify-content: center;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 5px;
  font-size: 11px;
}

.summary-rows > div + div {
  border-left: 1px solid rgba(255, 255, 255, 0.14);
}

.summary-rows dt {
  color: #cbd5e1;
  font-weight: 500;
}

.summary-rows strong {
  color: #ffffff;
  font-weight: 700;
}

.submit-list {
  width: 100%;
  margin-top: 12px !important;
}

.inline-skeleton {
  display: inline-block;
  width: 42px;
  height: 13px;
  vertical-align: middle;
  border-radius: 4px;
  background: #e2e8f0;
  animation: sidebar-pulse 1.4s ease-in-out infinite;
}

.inline-skeleton.wide {
  width: 94px;
}

@keyframes sidebar-pulse {
  50% {
    opacity: 0.55;
  }
}

@media (min-width: 1000px) {
  .list-sidebar {
    position: sticky;
    top: 24px;
  }
}

@media (max-width: 999px) {
  .sidebar-card {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
  .note-section {
    border-top: 0 !important;
    border-left: 1px solid #f1f5f9;
  }
  .order-channel-section,
  .summary-section {
    grid-column: 1 / -1;
  }
  .summary-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }
  .summary-value {
    flex: 1;
  }
  .submit-list {
    width: auto;
    min-width: 210px;
    margin-top: 0 !important;
  }
}

@media (max-width: 620px) {
  .sidebar-card {
    grid-template-columns: 1fr;
  }
  .sidebar-section {
    padding: 14px;
  }
  .note-section {
    border-left: 0;
    border-top: 1px solid #f1f5f9 !important;
  }
  .summary-total {
    display: block;
  }
  .submit-list {
    width: 100%;
    margin-top: 12px !important;
  }
}
</style>
