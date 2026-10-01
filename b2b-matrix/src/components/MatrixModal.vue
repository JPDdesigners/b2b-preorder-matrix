<template>
  <DialogModal open wide tablet-fullscreen label="Επεξεργασία προϊόντος" @close="$emit('close')">
    <div class="b2b-matrix-modal-dialog">
      <!-- Modal Header -->
      <div class="b2b-matrix-modal-header">
        <div class="modal-heading"><button type="button" class="back-to-list" @click="$emit('close')"><UiIcon name="chevron-left" />Πίσω στη λίστα</button><span>Επεξεργασία ποσοτήτων</span></div>
        <button
          type="button"
          class="b2b-modal-close-btn"
          aria-label="Κλείσιμο"
          @click="$emit('close')"
        >
          <UiIcon name="close" class="b2b-u-w-5 b2b-u-h-5" />
        </button>
      </div>

      <!-- Modal Body (Housing the actual App / MatrixTable) -->
      <div class="b2b-matrix-modal-body">
        <section
          class="b2b-matrix-app"
          aria-label="Λίστα παραγγελίας"
          :style="themeStyle"
          :aria-busy="store.loading || store.isSubmitting"
        >
          <ImageLightbox :image="store.activeImage" @close="store.closeLightbox()" />

          <div v-if="store.loadError" class="empty-state" role="alert">
            <h3>Δεν φορτώθηκε η λίστα</h3>
            <p>{{ store.loadError }}</p>
            <B2BButton variant="primary" size="md" @click="store.fetchMatrix()">Νέα προσπάθεια</B2BButton>
          </div>

          <div v-else-if="store.loading && !store.data" class="loading-state" role="status">
            <div v-for="i in 4" :key="i" class="skeleton-row"></div>
            <span>Φόρτωση πίνακα παραλλαγών…</span>
          </div>

          <template v-else-if="store.data">
            <div v-if="store.ready && store.unavailableCount" class="unavailable-notice" role="status">
              {{ store.unavailableCount }} αποθηκευμένες παραλλαγές δεν εμφανίζονται πλέον στον πίνακα.
            </div>
            <MatrixTable :store="store" :pending="store.loading || !store.ready" />
            <div v-if="store.ready && !store.cells.length" class="empty-state">
              Δεν υπάρχουν συνδυασμοί χρώματος και μεγέθους.
            </div>

            <!-- Footer of MatrixTable -->
            <footer class="matrix-footer">
              <div class="order-summary">
                <span class="summary-label">Σύνολο επιλογής · ενδεικτική αξία</span>
                <div v-if="store.ready" class="summary-numbers">
                  <strong>{{ store.totalQty }} <small>τεμ.</small></strong>
                  <span class="summary-divider"></span>
                  <strong>{{ store.totalPrice }}</strong>
                </div>
                <div v-else class="summary-numbers">
                  <span class="summary-skeleton"></span>
                  <span class="summary-divider"></span>
                  <span class="summary-skeleton"></span>
                </div>
                <p v-if="store.ready && (store.hasExistingItems || store.isDirty)" class="previous-total">
                  Αποθηκευμένα: {{ store.previousTotalQty }} τεμ. <span>·</span> {{ store.previousTotalPrice }}
                  <B2BBadge v-if="store.isDirty" variant="primary" size="sm">
                    {{ store.deltaQty > 0 ? '+' : '' }}{{ store.deltaQty }} τεμ. / {{ store.deltaPrice }}
                  </B2BBadge>
                </p>
              </div>

              <div class="footer-actions">
                <span
                  class="save-state"
                  :class="{
                    'is-pending': store.ready && store.isDirty,
                    'is-error': store.feedbackStatus === 'error',
                    'is-success': store.ready && store.canSave && !store.isDirty && store.feedbackStatus !== 'error'
                  }"
                  role="status"
                >
                  <UiIcon :name="store.feedbackStatus === 'error' ? 'alert' : !store.ready || store.isDirty ? 'clock' : 'check-circle'" />
                  {{
                    !store.ready ? 'Φόρτωση ποσοτήτων και τιμών…' :
                    store.isSubmitting ? 'Αποθήκευση…' :
                    store.feedbackStatus === 'error' ? (store.feedbackMsg || 'Ελέγξτε τις αλλαγές σας') :
                    store.isDirty ? 'Μη αποθηκευμένες αλλαγές' : 'Η λίστα είναι ενημερωμένη'
                  }}
                </span>

                <B2BButton
                  variant="primary"
                  size="lg"
                  :disabled="!store.ready || !store.canSave || !store.isDirty || store.isSubmitting"
                  :loading="store.isSubmitting"
                  @click="handleSave"
                >
                  <template #icon>
                    <UiIcon :name="!store.isDirty && store.feedbackStatus === 'success' ? 'check' : 'save'" />
                  </template>
                  {{ store.isSubmitting ? 'Αποθήκευση…' : !store.isDirty && store.feedbackStatus === 'success' ? 'Αποθηκεύτηκε' : 'Αποθήκευση' }}
                </B2BButton>
              </div>
            </footer>
          </template>
        </section>
      </div>
    </div>
  </DialogModal>
</template>

<script setup>
import { computed, provide } from 'vue';
import { appearanceStyle, SYSTEM_FONT, THEME_KEY } from '../theme.js';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import B2BBadge from './ui/B2BBadge.vue';
import MatrixTable from './MatrixTable.vue';
import ImageLightbox from './ImageLightbox.vue';
import DialogModal from './DialogModal.vue';

const props = defineProps({
  store: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const themeStyle = computed(() => appearanceStyle(props.store.appearance, SYSTEM_FONT));
provide(THEME_KEY, themeStyle);

async function handleSave() {
  const saved = await props.store.submitOrder();
  if (saved) {
    emit('saved', {
      productId: props.store.productId,
      saved,
      listPatch: props.store.savedListPatch,
      totalQty: props.store.totalQty,
      totalPriceRaw: props.store.currentTotalPriceRaw,
      name: props.store.data?.product_name || '',
      sku: props.store.data?.parent_sku || '',
    });
  }
}
</script>

<style scoped>
.b2b-matrix-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(5px);
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.b2b-matrix-modal-dialog {
  background: #ffffff;
  border-radius: var(--b2b-radius-lg, 14px);
  box-shadow: 0 25px 60px -12px rgba(0, 0, 0, 0.35);
  width: 100%;
  max-height: calc(100dvh - 48px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.modal-heading { display: flex; align-items: center; gap: 18px; min-width: 0; }
.modal-heading > span { color: #64748b; font-size: 12px; font-weight: 600; }
.back-to-list { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: var(--b2b-radius, 8px); background: #fff; color: #1e293b; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; }
.back-to-list:hover { background: #f8fafc; border-color: var(--b2b-primary-border); color: var(--b2b-primary); }
.back-to-list :deep(svg) { width: 17px; height: 17px; }

.b2b-matrix-modal-header {
  padding: 16px 24px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}

.b2b-modal-close-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 8px;
  border-radius: 8px;
  color: #475569;
  min-width: 44px;
  min-height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.b2b-modal-close-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.b2b-matrix-modal-body {
  overflow-y: auto;
  flex: 1;
  padding: 16px;
  background: #f8fafc;
}

/* Inherit app wrapper styling from App.vue */
.b2b-matrix-app {
  color: #0f172a;
  background: #fff;
  border: 1px solid #d4d4d8;
  border-radius: var(--b2b-radius-lg, 12px);
  margin: 0;
  overflow: hidden;
  font-family: var(--b2b-font, inherit);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 8px 24px -4px rgba(0, 0, 0, 0.07);
  font-variant-numeric: tabular-nums;
  box-sizing: border-box;
}

.b2b-matrix-app :deep(table) {
  border: 0 !important;
  margin: 0 !important;
  background: transparent !important;
}

.b2b-matrix-app :deep(*) {
  box-sizing: border-box;
}

.b2b-matrix-app :deep(button),
.b2b-matrix-app :deep(input),
.b2b-matrix-app :deep(label),
.b2b-matrix-app :deep(select) {
  font-family: inherit;
}

.matrix-footer {
  background: #f8fafc;
  padding: 22px 24px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px 24px;
  border-top: 1px solid #e2e8f0;
}

.order-summary {
  display: flex;
  flex-direction: column;
}

.summary-label {
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.summary-numbers {
  display: flex;
  gap: 18px;
  align-items: center;
  margin-top: 6px;
}

.summary-numbers strong {
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -.5px;
  line-height: 1.2;
  color: #0f172a;
}

.summary-numbers small {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.summary-divider {
  width: 1px;
  height: 24px;
  background: #cbd5e1;
}

.previous-total {
  font-size: 12px;
  color: #64748b;
  margin: 6px 0 0;
  display: flex;
  align-items: center;
  gap: 6px;
}

.footer-actions {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.save-state {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 0 !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  font-size: 12px;
  font-weight: 500;
  color: #64748b !important;
}

.save-state.is-pending {
  color: #d97706 !important;
}

.save-state.is-success {
  color: #16a34a !important;
}

.save-state.is-error {
  color: #dc2626 !important;
}

.empty-state {
  padding: 48px 24px;
  text-align: center;
  color: #64748b;
}

.empty-state h3 {
  font-size: 16px;
  color: #0f172a;
  margin-bottom: 8px;
}

.loading-state {
  padding: 32px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  color: #64748b;
}

.skeleton-row {
  width: 100%;
  height: 48px;
  background: #f1f5f9;
  border-radius: var(--b2b-radius, 6px);
  animation: pulse 1.5s infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .4; }
}

.unavailable-notice {
  background: #fffbeb;
  border-bottom: 1px solid #fef3c7;
  color: #92400e;
  padding: 10px 16px;
  font-size: 12px;
  line-height: 1.4;
}
@media (max-width: 1024px) {
  .b2b-matrix-modal-dialog { flex: 1; min-height: 0; max-height: none; border: 0; border-radius: 0; box-shadow: none; }
  .b2b-matrix-modal-header { padding: 10px 16px; }
  .b2b-matrix-modal-body { display: flex; min-height: 0; padding: 0; overflow: hidden; }
  .b2b-matrix-app { display: flex; flex-direction: column; flex: 1; min-height: 0; width: 100%; border: 0; border-radius: 0; box-shadow: none; }
  .b2b-matrix-app :deep(.matrix-table-outer) { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior-y: contain; }
  .matrix-footer { flex: 0 0 auto; padding: 12px 16px; box-shadow: 0 -4px 16px rgba(15,23,42,.06); }
}
@media (max-width: 600px) {
  .modal-heading > span { display: none; }
  .b2b-matrix-modal-header { padding: 8px 10px; }
  .matrix-footer { gap: 10px; padding: 10px; }
  .summary-numbers strong { font-size: 19px; }
  .previous-total { display: none; }
}
</style>
