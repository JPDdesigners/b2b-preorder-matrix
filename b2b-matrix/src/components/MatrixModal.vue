<template>
  <DialogModal open fullscreen label="Επεξεργασία κωδικού" @close="$emit('close')">
    <div class="b2b-matrix-modal-dialog">
      <!-- Modal Header -->
      <div class="b2b-matrix-modal-header">
        <div class="modal-heading">
          <button type="button" class="back-to-list" @click="$emit('close')">
            <UiIcon name="chevron-left" />
            Πίσω στη λίστα
          </button>
          <span>Επεξεργασία ποσοτήτων</span>
        </div>
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
          ref="app"
          class="b2b-matrix-app"
          aria-label="Λίστα παραγγελίας"
          :style="themeStyle"
          :aria-busy="store.loading || store.isSubmitting"
        >
          <ImageLightbox :image="store.activeImage" @close="store.closeLightbox()" />

          <div v-if="store.loadError" class="empty-state" role="alert">
            <h3>Δεν φορτώθηκε η λίστα</h3>
            <p>{{ store.loadError }}</p>
            <B2BButton variant="primary" size="md" @click="store.fetchMatrix()">
              Νέα προσπάθεια
            </B2BButton>
          </div>

          <div v-else-if="store.loading && !store.data" class="loading-state" role="status">
            <div v-for="i in 4" :key="i" class="skeleton-row"></div>
            <span>Φόρτωση πίνακα παραλλαγών…</span>
          </div>

          <template v-else-if="store.data">
            <div
              v-if="store.ready && store.unavailableCount"
              class="unavailable-notice"
              role="status"
            >
              {{ store.unavailableCount }} αποθηκευμένες παραλλαγές δεν εμφανίζονται πλέον στον πίνακα.
            </div>

            <MatrixTable :store="store" :pending="store.loading || !store.ready" />

            <div v-if="store.ready && !store.cells.length" class="empty-state">
              Δεν υπάρχουν συνδυασμοί χρώματος και μεγέθους.
            </div>

            <MatrixFooter
              :store="store"
              compact
              save-label="Αποθήκευση"
              @save="handleSave"
            />
          </template>
        </section>
      </div>
    </div>
  </DialogModal>
</template>

<script setup>
import { computed, provide, ref, onMounted, onUnmounted } from 'vue';
import { appearanceStyle, SYSTEM_FONT, THEME_KEY } from '../theme.js';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import MatrixTable from './MatrixTable.vue';
import MatrixFooter from './MatrixFooter.vue';
import ImageLightbox from './ImageLightbox.vue';
import DialogModal from './DialogModal.vue';

const props = defineProps({
  store: { type: Object, required: true },
});

const emit = defineEmits(['close', 'saved']);

const themeStyle = computed(() => appearanceStyle(props.store.appearance, SYSTEM_FONT));
provide(THEME_KEY, themeStyle);

// Desktop: the wheel over the side margins, the top bar or the footer scrolls the colour list,
// so there is always a place to scroll that can never change a quantity.
const app = ref(null);
function forwardWheel(event) {
  const root = app.value;
  const scroller = root?.querySelector('.matrix-table-outer');
  if (!root || !scroller || event.defaultPrevented || event.ctrlKey || event.metaKey) return;
  if (scroller.contains(event.target) || event.target.closest?.('dialog') !== root.closest('dialog')) {
    return;
  }
  const unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? scroller.clientHeight : 1;
  const top = (event.shiftKey ? 0 : event.deltaY) * unit;
  if (!top) return;
  event.preventDefault();
  scroller.scrollBy({ top });
}

onMounted(() => window.addEventListener('wheel', forwardWheel, { passive: false }));
onUnmounted(() => window.removeEventListener('wheel', forwardWheel));

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
.b2b-matrix-modal-dialog {
  box-sizing: border-box;
  background: #ffffff;
  width: 100%;
  max-width: calc(80rem - 24px);
  flex: 0 1 auto;
  max-height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-inline: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
  border-radius: 0 0 var(--b2b-radius-lg, 14px) var(--b2b-radius-lg, 14px);
  box-shadow: 0 12px 32px -8px rgba(15, 23, 42, 0.08);
}

.modal-heading {
  display: flex;
  align-items: center;
  gap: 18px;
  min-width: 0;
}

.modal-heading > span {
  color: #64748b;
  font-size: 12px;
  font-weight: 600;
}

.back-to-list {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid #cbd5e1;
  border-radius: var(--b2b-radius, 8px);
  background: #ffffff;
  color: #1e293b;
  font: inherit;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
}

.back-to-list:hover {
  background: #f8fafc;
  border-color: var(--b2b-primary-border);
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.back-to-list :deep(svg) {
  width: 17px;
  height: 17px;
}

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
  display: flex;
  min-height: 0;
  overflow: hidden;
  flex: 0 1 auto;
  padding: 0;
  background: #ffffff;
}

.b2b-matrix-app {
  display: flex;
  flex-direction: column;
  flex: 0 1 auto;
  min-height: 0;
  width: 100%;
  color: #0f172a;
  background: #ffffff;
  border: 0;
  border-radius: 0;
  margin: 0;
  overflow: hidden;
  font-family: var(--b2b-font, inherit);
  font-variant-numeric: tabular-nums;
  box-sizing: border-box;
}

/* One vertical scroller: the editor body. When there are few colours, the footer sits
   directly below the last colour row; when colours exceed the screen, the footer stays
   at the bottom edge and the docked size header locks at the top edge while scrolling. */
.b2b-matrix-app :deep(.matrix-table-outer) {
  flex: 0 1 auto;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior-y: contain;
  -webkit-overflow-scrolling: touch;
}

.b2b-matrix-app > .loading-state,
.b2b-matrix-app > .empty-state {
  overflow-y: auto;
}

.unavailable-notice {
  flex-shrink: 0;
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
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.4;
  }
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
  .b2b-matrix-modal-header {
    padding: 10px 16px;
  }
}

@media (max-width: 600px) {
  .b2b-matrix-modal-dialog {
    border: 0;
  }
  .modal-heading > span {
    display: none;
  }
  .b2b-matrix-modal-header {
    padding: 8px 10px;
  }
}
</style>
