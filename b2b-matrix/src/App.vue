<template>
  <section class="b2b-matrix-app" aria-label="Λίστα παραγγελίας" :style="themeStyle" :aria-busy="store.loading || store.isSubmitting">
    <ImageLightbox :image="store.activeImage" @close="store.closeLightbox()" />
    <div v-if="store.loadError" class="empty-state" role="alert">
      <h3>Δεν φορτώθηκε η λίστα</h3><p>{{ store.loadError }}</p>
      <B2BButton variant="primary" size="md" @click="store.fetchMatrix()">Νέα προσπάθεια</B2BButton>
    </div>
    <template v-if="store.data">
      <div v-if="store.ready && store.unavailableCount" class="unavailable-notice" role="status">{{ store.unavailableCount }} αποθηκευμένες παραλλαγές δεν εμφανίζονται πλέον στον πίνακα. Διατηρούνται στη λίστα σας και δεν περιλαμβάνονται στα παρακάτω σύνολα.</div>
      <MatrixTable :store="store" :pending="!store.ready" />
      <div v-if="store.ready && !store.cells.length" class="empty-state">Δεν υπάρχουν συνδυασμοί χρώματος και μεγέθους.</div>
      <footer class="matrix-footer">
        <div class="order-summary">
          <span class="summary-label">Σύνολο επιλογής · ενδεικτική αξία</span>
          <div v-if="store.ready" class="summary-numbers"><strong>{{ store.totalQty }} <small>τεμ.</small></strong><span class="summary-divider"></span><strong>{{ store.totalPrice }}</strong></div>
          <div v-else class="summary-numbers" aria-label="Αναμονή ποσοτήτων και τιμών"><span class="summary-skeleton"></span><span class="summary-divider"></span><span class="summary-skeleton"></span></div>
          <p v-if="store.ready && (store.hasExistingItems || store.isDirty)" class="previous-total">
            Αποθηκευμένα: {{ store.previousTotalQty }} τεμ. <span>·</span> {{ store.previousTotalPrice }}
            <B2BBadge v-if="store.isDirty" variant="primary" size="sm">
              {{ store.deltaQty > 0 ? '+' : '' }}{{ store.deltaQty }} τεμ. / {{ store.deltaPrice }}
            </B2BBadge>
          </p>
        </div>
        <div class="footer-actions">
          <span class="save-state" :class="{ 'is-pending': store.ready && store.isDirty, 'is-error': store.feedbackStatus === 'error', 'is-success': store.ready && store.canSave && !store.isDirty && store.feedbackStatus !== 'error' }" role="status">
            <UiIcon :name="store.feedbackStatus === 'error' ? 'alert' : !store.ready || store.isDirty ? 'clock' : 'check-circle'" />
            {{ !store.ready ? (store.loadError ? 'Η λίστα δεν είναι διαθέσιμη' : 'Φόρτωση ποσοτήτων και τιμών…') : !store.canSave ? 'Αποθήκευση μόνο για συνδεδεμένους χρήστες' : store.isSubmitting ? 'Αποθήκευση…' : store.feedbackStatus === 'error' ? (store.feedbackMsg || 'Ελέγξτε τις αλλαγές σας') : store.isDirty ? 'Μη αποθηκευμένες αλλαγές' : 'Η λίστα είναι ενημερωμένη' }}
          </span>
          <B2BButton
            variant="primary"
            size="lg"
            :disabled="!store.ready || !store.canSave || !store.isDirty || store.isSubmitting"
            :loading="store.isSubmitting"
            @click="store.submitOrder()"
          >
            <template #icon>
              <UiIcon :name="!store.isDirty && store.feedbackStatus === 'success' ? 'check' : 'save'" />
            </template>
            {{ store.isSubmitting ? 'Αποθήκευση…' : !store.isDirty && store.feedbackStatus === 'success' ? 'Αποθηκεύτηκε' : 'Αποθήκευση λίστας' }}
          </B2BButton>
          <a v-if="store.ready && !store.canSave && store.loginUrl" class="login-link" :href="store.loginUrl">Σύνδεση στον λογαριασμό</a>
        </div>
      </footer>
    </template>
    <div v-else-if="!store.loadError" class="loading-state" role="status"><div v-for="i in 4" :key="i" class="skeleton-row"></div><span>Φόρτωση πίνακα…</span></div>
  </section>
</template>

<script setup>
import { computed, ref, provide, onMounted, onUnmounted } from 'vue';
import { appearanceStyle, SYSTEM_FONT, THEME_KEY } from './theme.js';
import UiIcon from './components/UiIcon.vue';
import B2BButton from './components/ui/B2BButton.vue';
import B2BBadge from './components/ui/B2BBadge.vue';
import { useMatrixStore } from './stores/matrixStore';
import MatrixTable from './components/MatrixTable.vue';
import ImageLightbox from './components/ImageLightbox.vue';
const props = defineProps({ container: { type: Object, required: true } });
const store = useMatrixStore();
store.initFromDOM(props.container);
const inheritedFont = ref(SYSTEM_FONT);
const themeStyle = computed(() => appearanceStyle(store.appearance, inheritedFont.value));
provide(THEME_KEY, themeStyle);
function beforeLeave(event) { if (store.isDirty || store.isSubmitting) { event.preventDefault(); event.returnValue = ''; } }
function restorePage(event) {
  if (!event.persisted) return;
  // A browser back/forward snapshot must not reuse a previous session's values.
  store.ready = false;
  store.cart = {};
  store.existingQuantities = {};
  window.location.reload();
}
onMounted(() => {
  inheritedFont.value = getComputedStyle(props.container).fontFamily || SYSTEM_FONT;
  store.fetchMatrix();
  window.addEventListener('beforeunload', beforeLeave);
  window.addEventListener('pageshow', restorePage);
});
onUnmounted(() => {
  window.removeEventListener('beforeunload', beforeLeave);
  window.removeEventListener('pageshow', restorePage);
});
</script>

<style scoped>
.b2b-matrix-app { color: #0f172a; background: #fff; border: 1px solid #d4d4d8; border-radius: var(--b2b-radius-lg, 12px); margin: 24px 0; overflow: hidden; font-family: var(--b2b-font, inherit); box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 8px 24px -4px rgba(0, 0, 0, 0.07); font-variant-numeric: tabular-nums; box-sizing: border-box; }
.b2b-matrix-app :deep(table) { border: 0 !important; margin: 0 !important; background: transparent !important; }
.b2b-matrix-app :deep(*) { box-sizing: border-box; }
.b2b-matrix-app :deep(button),
.b2b-matrix-app :deep(input),
.b2b-matrix-app :deep(label),
.b2b-matrix-app :deep(select) {
  font-family: inherit;
}
.matrix-footer { background: #f8fafc; padding: 22px 24px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 20px 24px; border-top: 1px solid #e2e8f0; }
.order-summary { display: flex; flex-direction: column; }
.summary-label { color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
.summary-numbers { display: flex; gap: 18px; align-items: center; margin-top: 6px; }
.summary-numbers strong { font-size: 26px; font-weight: 700; letter-spacing: -.5px; line-height: 1.2; color: #0f172a; }
.summary-numbers small { font-size: 13px; font-weight: 500; color: #64748b; }
.summary-skeleton { width: 110px; height: 31px; border-radius: var(--b2b-radius-sm); background: #e2e8f0; animation: pulse 1.5s ease infinite alternate; }
.login-link { color: var(--b2b-primary); font-size: 12px; }
.summary-divider { height: 26px; width: 1px; background: #e2e8f0; }
.previous-total { font-size: 11px; color: #64748b; margin: 8px 0 0; min-height: 22px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.delta { padding: 2px 7px; background: var(--b2b-primary-soft); border: 1px solid var(--b2b-primary-border); border-radius: var(--b2b-radius-sm, 4px); color: var(--b2b-primary); font-weight: 600; }
.footer-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; }
.save-state { min-height: 22px; display: inline-flex; align-items: center; gap: 6px; padding: 0 !important; border: 0 !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; font-size: 11px; font-weight: 500; line-height: 1.2; color: #64748b !important; transition: color .15s ease; }
.save-state.is-pending { color: #d97706 !important; }
.save-state.is-success { color: #15803d !important; font-weight: 600; }
.save-state.is-error { color: #dc2626 !important; font-weight: 600; }
.save-state :deep(svg) { width: 14px; height: 14px; flex-basis: 14px; }
.primary-button { border: 1px solid var(--b2b-primary); background: var(--b2b-primary); color: #fff; border-radius: var(--b2b-radius, 8px); min-height: 48px; padding: 0 24px; font: inherit; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 9px; cursor: pointer; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06); transition: all .15s ease; }
.primary-button:hover:not(:disabled) { background: var(--b2b-primary-hover); box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15); }
.primary-button:disabled { opacity: .45; box-shadow: none; cursor: default; }
button:focus-visible { outline: 2px solid var(--b2b-primary); outline-offset: 3px; }
.unavailable-notice { margin: 0; padding: 14px 24px; font-size: 12px; line-height: 1.6; background: #fffbeb; border-bottom: 1px solid #fef3c7; color: #92400e; }
.empty-state { padding: 48px 24px; text-align: center; border-top: 1px solid #e2e8f0; }
.empty-state h3 { font-size: 18px; font-weight: 600; color: #0f172a; }
.empty-state p { font-size: 13px; color: #64748b; line-height: 1.6; }
.empty-state button { margin: 12px auto 0; }
.loading-state { padding: 24px; color: #64748b; font-size: 12px; }
.skeleton-row { height: 76px; margin-bottom: 12px; border-radius: var(--b2b-radius-input, 8px); background: #f1f5f9; animation: pulse 1.5s ease infinite alternate; }
.spinner { width: 14px; height: 14px; border: 2px solid #ffffff50; border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes pulse { to { opacity: .4; } }
@keyframes spin { to { transform: rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spinner, .skeleton-row, .summary-skeleton { animation: none; } }
@media (max-width: 900px) {
  .matrix-footer { padding: 18px 16px; }
}
@media (max-width: 600px) {
  .b2b-matrix-app { border-radius: var(--b2b-radius-mobile, 8px); margin: 16px 0; }
  .matrix-footer { padding: 16px 12px; }
  .summary-numbers strong { font-size: 22px; }
  .footer-actions { width: 100%; justify-content: space-between; }
  .primary-button { width: 100%; }
}
</style>
