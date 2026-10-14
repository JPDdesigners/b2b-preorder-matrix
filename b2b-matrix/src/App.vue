<template>
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
      <B2BButton variant="primary" size="md" @click="store.fetchMatrix()">
        Νέα προσπάθεια
      </B2BButton>
    </div>

    <template v-if="store.data">
      <div v-if="store.ready && store.unavailableCount" class="unavailable-notice" role="status">
        {{ store.unavailableCount }} αποθηκευμένες παραλλαγές δεν εμφανίζονται πλέον στον πίνακα.
        Διατηρούνται στη λίστα σας και δεν περιλαμβάνονται στα παρακάτω σύνολα.
      </div>

      <MatrixTable :store="store" :pending="!store.ready" page-sticky />

      <div v-if="store.ready && !store.cells.length" class="empty-state">
        Δεν υπάρχουν συνδυασμοί χρώματος και μεγέθους.
      </div>

      <MatrixFooter
        :store="store"
        sticky
        show-login-link
        save-label="Αποθήκευση λίστας"
        @save="store.submitOrder()"
      />
    </template>

    <div v-else-if="!store.loadError" class="loading-state" role="status">
      <div v-for="i in 4" :key="i" class="skeleton-row"></div>
      <span>Φόρτωση πίνακα…</span>
    </div>
  </section>
</template>

<script setup>
import { computed, ref, provide, onMounted, onUnmounted } from 'vue';
import { appearanceStyle, SYSTEM_FONT, THEME_KEY } from './theme.js';
import B2BButton from './components/ui/B2BButton.vue';
import { useMatrixStore } from './stores/matrixStore';
import MatrixTable from './components/MatrixTable.vue';
import MatrixFooter from './components/MatrixFooter.vue';
import ImageLightbox from './components/ImageLightbox.vue';

const props = defineProps({
  container: { type: Object, required: true },
});

const store = useMatrixStore();
store.initFromDOM(props.container);

const inheritedFont = ref(SYSTEM_FONT);
const themeStyle = computed(() => appearanceStyle(store.appearance, inheritedFont.value));
provide(THEME_KEY, themeStyle);

function beforeLeave(event) {
  if (store.isDirty || store.isSubmitting) {
    event.preventDefault();
    event.returnValue = '';
  }
}

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
.b2b-matrix-app {
  color: #0f172a;
  background: #ffffff;
  border: 1px solid #d4d4d8;
  border-radius: var(--b2b-radius-lg, 12px);
  margin: 24px 0;
  overflow: hidden;
  font-family: var(--b2b-font, inherit);
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 8px 24px -4px rgba(0, 0, 0, 0.07);
  font-variant-numeric: tabular-nums;
  box-sizing: border-box;
  /* clip (not hidden) keeps the rounded card without creating a scroll container, so the size header
     and the footer can stick to the window while the customer scrolls the page. */
  overflow: clip;
}

@supports not (overflow: clip) {
  .b2b-matrix-app {
    overflow: visible;
  }
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

button:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: 3px;
}

.unavailable-notice {
  margin: 0;
  padding: 14px 24px;
  font-size: 12px;
  line-height: 1.6;
  background: #fffbeb;
  border-bottom: 1px solid #fef3c7;
  color: #92400e;
}

.empty-state {
  padding: 48px 24px;
  text-align: center;
  border-top: 1px solid #e2e8f0;
}

.empty-state h3 {
  font-size: 18px;
  font-weight: 600;
  color: #0f172a;
}

.empty-state p {
  font-size: 13px;
  color: #64748b;
  line-height: 1.6;
}

.empty-state button {
  margin: 12px auto 0;
}

.loading-state {
  padding: 24px;
  color: #64748b;
  font-size: 12px;
}

.skeleton-row {
  height: 76px;
  margin-bottom: 12px;
  border-radius: var(--b2b-radius-input, 8px);
  background: #f1f5f9;
  animation: pulse 1.5s ease infinite alternate;
}

@keyframes pulse {
  to {
    opacity: 0.4;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-row {
    animation: none;
  }
}

@media (max-width: 600px) {
  .b2b-matrix-app {
    border-radius: var(--b2b-radius-mobile, 8px);
    margin: 16px 0;
  }
}
</style>
