<template>
  <footer
    ref="footer"
    class="matrix-footer"
    :class="{
      'is-sticky': sticky,
      'is-stuck': sticky && footerStuck,
      'is-modal': compact,
    }"
  >
    <div class="order-summary">
      <span class="summary-label">Σύνολο επιλογής · συνολική αξία</span>

      <div v-if="store.ready" class="summary-numbers">
        <strong>{{ store.totalQty }} <small>τεμ.</small></strong>
        <span class="summary-divider"></span>
        <strong>{{ store.totalPrice }}</strong>
      </div>
      <div v-else class="summary-numbers" aria-label="Αναμονή ποσοτήτων και τιμών">
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
          'is-success':
            store.ready && store.canSave && !store.isDirty && store.feedbackStatus !== 'error',
        }"
        role="status"
      >
        <UiIcon
          :name="
            store.feedbackStatus === 'error'
              ? 'alert'
              : !store.ready || store.isDirty
                ? 'clock'
                : 'check-circle'
          "
        />
        {{ statusText }}
      </span>

      <B2BButton
        variant="primary"
        size="lg"
        :disabled="!store.ready || !store.canSave || !store.isDirty || store.isSubmitting"
        :loading="store.isSubmitting"
        @click="$emit('save')"
      >
        <template #icon>
          <UiIcon :name="!store.isDirty && store.feedbackStatus === 'success' ? 'check' : 'save'" />
        </template>
        {{
          store.isSubmitting
            ? 'Αποθήκευση…'
            : !store.isDirty && store.feedbackStatus === 'success'
              ? 'Αποθηκεύτηκε'
              : saveLabel
        }}
      </B2BButton>

      <a
        v-if="showLoginLink && store.ready && !store.canSave && store.loginUrl"
        class="login-link"
        :href="store.loginUrl"
      >
        Σύνδεση στον λογαριασμό
      </a>
    </div>
  </footer>
</template>

<script setup>
import { computed, ref, onMounted, onUpdated, onUnmounted } from 'vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import B2BBadge from './ui/B2BBadge.vue';

const props = defineProps({
  store: { type: Object, required: true },
  sticky: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
  saveLabel: { type: String, default: 'Αποθήκευση λίστας' },
  showLoginLink: { type: Boolean, default: false },
});

defineEmits(['save']);

const statusText = computed(() => {
  if (!props.store.ready) {
    return props.store.loadError
      ? 'Η λίστα δεν είναι διαθέσιμη'
      : 'Φόρτωση ποσοτήτων και τιμών…';
  }
  if (!props.store.canSave) {
    return 'Αποθήκευση μόνο για συνδεδεμένους χρήστες';
  }
  if (props.store.isSubmitting) {
    return 'Αποθήκευση…';
  }
  if (props.store.feedbackStatus === 'error') {
    return props.store.feedbackMsg || 'Ελέγξτε τις αλλαγές σας';
  }
  if (props.store.isDirty) {
    return 'Μη αποθηκευμένες αλλαγές';
  }
  return 'Η λίστα είναι ενημερωμένη';
});

const footer = ref(null);
const footerStuck = ref(false);
let footerFrame = 0;

function measureFooter() {
  footerFrame = 0;
  if (!props.sticky) {
    footerStuck.value = false;
    return;
  }
  const el = footer.value;
  const card = el?.parentElement;
  if (!el || !card) {
    footerStuck.value = false;
    return;
  }
  const naturalBottom =
    card.getBoundingClientRect().bottom - (parseFloat(getComputedStyle(card).borderBottomWidth) || 0);
  footerStuck.value = naturalBottom - el.getBoundingClientRect().bottom > 1;
}

function scheduleFooter() {
  if (!props.sticky || footerFrame) return;
  footerFrame = requestAnimationFrame(measureFooter);
}

onMounted(() => {
  if (!props.sticky) return;
  window.addEventListener('scroll', scheduleFooter, { passive: true });
  window.addEventListener('resize', scheduleFooter, { passive: true });
  scheduleFooter();
});

onUpdated(scheduleFooter);

onUnmounted(() => {
  window.removeEventListener('scroll', scheduleFooter);
  window.removeEventListener('resize', scheduleFooter);
  cancelAnimationFrame(footerFrame);
});
</script>

<style scoped>
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

.matrix-footer.is-sticky {
  position: sticky;
  bottom: 0;
  z-index: 7;
  transition: box-shadow 0.2s ease;
}

.matrix-footer.is-stuck {
  box-shadow: 0 -10px 18px -10px rgba(15, 23, 42, 0.22);
}

.matrix-footer.is-modal {
  flex: 0 0 auto;
  box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
}

@media (max-height: 500px) {
  .matrix-footer.is-sticky {
    position: static;
  }
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
  letter-spacing: 0.05em;
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
  letter-spacing: -0.5px;
  line-height: 1.2;
  color: #0f172a;
}

.summary-numbers small {
  font-size: 13px;
  font-weight: 500;
  color: #64748b;
}

.summary-skeleton {
  width: 110px;
  height: 31px;
  border-radius: var(--b2b-radius-sm);
  background: #e2e8f0;
  animation: footer-pulse 1.5s ease infinite alternate;
}

@keyframes footer-pulse {
  to {
    opacity: 0.4;
  }
}

@media (prefers-reduced-motion: reduce) {
  .summary-skeleton {
    animation: none;
  }
}

.summary-divider {
  height: 24px;
  width: 1px;
  background: #cbd5e1;
}

.previous-total {
  font-size: 12px;
  color: #64748b;
  margin: 6px 0 0;
  min-height: 22px;
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.footer-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 14px;
}

.save-state {
  min-height: 22px;
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
  line-height: 1.2;
  color: #64748b !important;
  transition: color 0.15s ease;
}

.save-state.is-pending {
  color: #d97706 !important;
}

.save-state.is-success {
  color: #15803d !important;
  font-weight: 600;
}

.save-state.is-error {
  color: #dc2626 !important;
  font-weight: 600;
}

.save-state :deep(svg) {
  width: 14px;
  height: 14px;
  flex-basis: 14px;
}

.login-link {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 12px;
}

@media (max-width: 1024px) {
  .matrix-footer.is-modal {
    padding: 12px 16px;
  }
}

@media (max-width: 900px) {
  .matrix-footer:not(.is-modal) {
    padding: 18px 16px;
  }
}

@media (max-width: 600px) {
  .matrix-footer {
    padding: 16px 12px;
  }
  .matrix-footer.is-modal {
    gap: 10px;
    padding: 10px;
  }
  .summary-numbers strong {
    font-size: 22px;
  }
  .matrix-footer.is-modal .summary-numbers strong {
    font-size: 19px;
  }
  .matrix-footer.is-modal .previous-total {
    display: none;
  }
  .matrix-footer:not(.is-modal) .footer-actions {
    width: 100%;
    justify-content: space-between;
  }
}
</style>
