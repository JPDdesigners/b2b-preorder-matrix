<template>
  <div class="product-viewport-shell">
    <div v-if="!items.length" class="empty-list-state">
      <div class="empty-list-icon">
        <UiIcon name="clipboard-check" />
      </div>
      <h3>Η λίστα σας είναι έτοιμη να γεμίσει</h3>
      <p>Αναζητήστε έναν κωδικό στην κορυφή και επιλέξτε ποσότητες στον πίνακα.</p>
    </div>

    <div v-else-if="!filtered.length" class="empty-filter-state">
      <p>Δεν υπάρχουν κωδικοί με αυτά τα φίλτρα.</p>
      <B2BButton variant="ghost" size="sm" @click="$emit('clearFilters')">
        Καθαρισμός φίλτρων
      </B2BButton>
    </div>

    <template v-else>
      <div
        class="product-columns-heading"
        :style="{ paddingRight: 22 + listScrollbarWidth + 'px' }"
        aria-hidden="true"
      >
        <span>Κωδικός &amp; Περιγραφή</span>
        <span>Τεμάχια</span>
        <span>Αξία</span>
        <span>Ενέργεια</span>
      </div>

      <div
        ref="listViewport"
        class="product-window"
        :style="{ height: listHeight + 'px' }"
        role="list"
        aria-label="Κωδικοί λίστας"
        tabindex="0"
        @scroll="onListScroll"
      >
        <div
          class="b2b-list-virtual-spacer"
          :style="{ height: filtered.length * rowHeight + 'px' }"
        >
          <div
            v-for="(item, index) in visibleItems"
            :key="item.id"
            class="b2b-list-virtual-row"
            :style="{
              top: (visibleRange.start + index) * rowHeight + 'px',
              height: rowHeight + 'px',
            }"
          >
            <article
              class="b2b-list-product-row"
              role="listitem"
              :aria-posinset="visibleRange.start + index + 1"
              :aria-setsize="filtered.length"
            >
              <div class="b2b-list-product-main">
                <div class="b2b-list-product-media">
                  <button
                    type="button"
                    class="b2b-list-product-remove"
                    :disabled="locked"
                    :aria-label="'Αφαίρεση ' + item.name"
                    title="Αφαίρεση κωδικού"
                    @click="$emit('remove', item)"
                  >
                    <UiIcon name="trash" />
                  </button>
                  <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.name"
                    loading="lazy"
                  />
                  <span v-else class="b2b-list-product-image-empty">
                    <UiIcon name="layers" />
                  </span>
                </div>

                <div class="b2b-list-product-info">
                  <p class="b2b-list-product-meta">
                    <span class="b2b-list-sku-pill" :title="item.sku">
                      {{ item.sku || 'Χωρίς κωδικό' }}
                    </span>
                    <span
                      v-if="item.brand_name"
                      class="b2b-list-brand-badge"
                      :title="item.brand_name"
                    >
                      {{ item.brand_name }}
                    </span>
                  </p>
                  <h3 :title="item.name">{{ item.name }}</h3>
                  <p
                    v-if="item.unavailable || item.unpriced"
                    class="b2b-list-product-warning"
                    :title="
                      item.unavailable
                        ? 'Περιέχει μη διαθέσιμες παραλλαγές'
                        : 'Δεν υπάρχει τιμή για όλες τις παραλλαγές'
                    "
                  >
                    <UiIcon name="alert" />
                    <span>
                      {{
                        item.unavailable
                          ? 'Περιέχει μη διαθέσιμες παραλλαγές'
                          : 'Δεν υπάρχει τιμή για όλες τις παραλλαγές'
                      }}
                    </span>
                  </p>
                </div>
              </div>

              <div class="b2b-list-product-column b2b-list-product-quantity">
                <span class="b2b-list-product-column-label">Τεμάχια</span>
                <span
                  v-if="loading"
                  class="inline-skeleton"
                  aria-label="Φόρτωση ποσότητας"
                ></span>
                <strong v-else class="b2b-list-qty-badge">{{ item.total_qty }}</strong>
              </div>

              <div class="b2b-list-product-column b2b-list-product-value">
                <span class="b2b-list-product-column-label">Αξία</span>
                <span
                  v-if="loading"
                  class="inline-skeleton wide"
                  aria-label="Φόρτωση αξίας"
                ></span>
                <strong v-else>{{ item.unpriced ? '—' : money(item.total_val) }}</strong>
              </div>

              <B2BButton
                class="b2b-list-product-edit"
                variant="outline"
                size="sm"
                icon="edit"
                :disabled="locked || item.unavailable"
                @click="$emit('edit', item.id)"
              >
                Αλλαγή
              </B2BButton>
            </article>
          </div>
        </div>
      </div>
    </template>

    <div v-if="items.length" class="list-bottom-bar">
      <p>Εμφάνιση {{ filtered.length }} από {{ items.length }} κωδικούς</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import { windowRange } from '../windowedList.js';

const props = defineProps({
  items: { type: Array, default: () => [] },
  filtered: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
  money: { type: Function, required: true },
  resetKey: { type: String, default: '' },
});

defineEmits(['edit', 'remove', 'clearFilters']);

const listViewport = ref(null);
const listScrollTop = ref(0);
const rowHeight = ref(96);
const listScrollbarWidth = ref(0);

let listResizeObserver;
watch(listViewport, element => {
  listResizeObserver?.disconnect();
  listScrollbarWidth.value = 0;
  if (!element) return;
  const alignColumns = () => {
    listScrollbarWidth.value = Math.max(0, element.offsetWidth - element.clientWidth);
  };
  listResizeObserver = new ResizeObserver(alignColumns);
  listResizeObserver.observe(element);
  alignColumns();
});

const listHeight = computed(() =>
  Math.min(576, props.filtered.length * rowHeight.value)
);

const visibleRange = computed(() =>
  windowRange(
    props.filtered.length,
    listScrollTop.value,
    listHeight.value,
    rowHeight.value
  )
);

const visibleItems = computed(() =>
  props.filtered.slice(visibleRange.value.start, visibleRange.value.end)
);

function syncRowHeight() {
  rowHeight.value = window.matchMedia('(max-width: 620px)').matches ? 156 : 96;
}

function onListScroll(event) {
  listScrollTop.value = event.target.scrollTop;
}

watch(
  () => props.resetKey,
  () => {
    listScrollTop.value = 0;
    if (listViewport.value) listViewport.value.scrollTop = 0;
  }
);

onMounted(() => {
  syncRowHeight();
  window.addEventListener('resize', syncRowHeight);
});

onUnmounted(() => {
  listResizeObserver?.disconnect();
  window.removeEventListener('resize', syncRowHeight);
});
</script>

<style scoped>
.product-viewport-shell {
  --list-product-columns: minmax(0, 1fr) 82px 116px 104px;
  --list-product-gap: 12px;
}

.empty-list-state {
  padding: 56px 24px;
  text-align: center;
}

.empty-list-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  margin: 0 auto 14px;
  border-radius: var(--b2b-radius-lg, 12px);
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.empty-list-state h3 {
  margin: 0 !important;
  font-size: 16px;
  font-weight: 700;
  color: #0f172a;
}

.empty-list-state p {
  margin: 6px 0 0 !important;
  font-size: 13px;
  color: #64748b;
}

.empty-filter-state {
  padding: 36px 24px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.product-columns-heading {
  display: grid;
  grid-template-columns: var(--list-product-columns);
  column-gap: var(--list-product-gap);
  align-items: center;
  padding: 8px 22px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
  color: #64748b;
  font-size: 10.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  text-align: center;
}

.product-columns-heading > span:first-child {
  text-align: left;
  padding-left: 40px;
}

.product-columns-heading > span:last-child {
  text-align: right;
}

.product-window {
  overflow-y: auto;
  overscroll-behavior-y: auto;
  scrollbar-width: thin;
  background: #ffffff;
}

.product-window:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: -2px;
}

.b2b-list-virtual-spacer {
  position: relative;
}

.b2b-list-virtual-row {
  position: absolute;
  inset-inline: 0;
  box-sizing: border-box;
}

.b2b-list-product-row {
  display: grid;
  grid-template-columns: var(--list-product-columns);
  column-gap: var(--list-product-gap);
  align-items: center;
  height: calc(100% - 8px);
  margin: 4px 10px;
  padding: 8px 12px;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius, 8px);
  background: #ffffff;
  box-sizing: border-box;
  overflow: hidden;
  transition: border-color 0.14s ease, background-color 0.14s ease, box-shadow 0.14s ease;
}

.b2b-list-product-row:hover {
  border-color: var(--b2b-primary-border);
  background: #f8fafc;
  box-shadow: 0 2px 6px rgba(15, 23, 42, 0.04);
}

.b2b-list-product-main {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  min-height: 0;
  margin: 0;
  padding: 0 8px 0 0;
}

.b2b-list-product-media {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
  margin: 0;
  padding: 0;
}

.b2b-list-product-media img,
.b2b-list-product-image-empty {
  display: block;
  width: 50px;
  height: 60px;
  flex: 0 0 50px;
  margin: 0;
  padding: 0;
  object-fit: cover;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius-sm, 6px);
  background: #f8fafc;
}

.b2b-list-product-image-empty {
  display: grid;
  place-items: center;
  color: #94a3b8;
}

.b2b-list-product-remove {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: var(--b2b-radius-sm, 6px);
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  transition: color 0.15s ease, background-color 0.15s ease, border-color 0.15s ease;
}

.b2b-list-product-remove:is(:hover, :focus-visible):not(:disabled) {
  color: #dc2626;
  background: #fff1f2;
  border-color: #fecaca;
}

.b2b-list-product-remove:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: 2px;
}

.b2b-list-product-remove:disabled {
  opacity: 0.4;
  cursor: default;
}

.b2b-list-product-remove :deep(svg) {
  width: 15px;
  height: 15px;
}

.b2b-list-product-info {
  min-width: 0;
  min-height: 0;
  margin: 0;
  padding: 0;
}

.b2b-list-product-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 4px !important;
  color: #64748b;
  font-size: 11px;
}

.b2b-list-sku-pill {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 1px 6px;
  border-radius: var(--b2b-radius-sm, 4px);
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #334155;
  font-weight: 700;
  font-size: 11px;
  font-variant-numeric: tabular-nums;
}

.b2b-list-brand-badge {
  flex: 0 0 auto;
  max-width: 110px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 1px 6px;
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius-sm, 4px);
  background: var(--b2b-primary-soft);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-weight: 600;
  font-size: 10.5px;
}

.b2b-list-product-info h3 {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  margin: 0 !important;
  color: #0f172a;
  font-size: 13.5px;
  line-height: 1.32;
  font-weight: 650;
}

.b2b-list-product-warning {
  display: flex;
  align-items: center;
  gap: 5px;
  margin: 4px 0 0 !important;
  color: #b45309;
  font-size: 11px;
  font-weight: 500;
}

.b2b-list-product-warning :deep(svg) {
  width: 13px;
  height: 13px;
}

.b2b-list-product-warning span {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.b2b-list-product-column {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 3px;
  min-width: 0;
  min-height: 48px;
  padding: 0 8px;
  border-left: 1px solid #f1f5f9;
  font-variant-numeric: tabular-nums;
}

.b2b-list-product-column strong {
  color: #0f172a;
  font-size: 13px;
  font-weight: 700;
  white-space: nowrap;
}

.b2b-list-qty-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 34px;
  padding: 2px 8px;
  border-radius: var(--b2b-radius-pill, 999px);
  background: #f1f5f9;
  color: #0f172a;
}

.b2b-list-product-column-label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
}

.b2b-list-product-edit {
  align-self: center;
  justify-self: end;
  min-height: 36px !important;
  padding: 0 13px !important;
  color: #334155 !important;
  border-color: #cbd5e1 !important;
  background: #ffffff !important;
}

.b2b-list-product-edit:is(:hover, :focus-visible):not(:disabled):not(.is-disabled) {
  color: var(--b2b-primary-ink, var(--b2b-primary)) !important;
  border-color: var(--b2b-primary-border) !important;
  background: var(--b2b-primary-soft) !important;
}

.list-bottom-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 10px 18px;
  background: #f8fafc;
  border-top: 1px solid #e2e8f0;
}

.list-bottom-bar p {
  margin: 0 !important;
  color: #64748b;
  font-size: 11px;
  font-weight: 500;
}

.inline-skeleton {
  display: inline-block;
  width: 42px;
  height: 13px;
  vertical-align: middle;
  border-radius: 4px;
  background: #e2e8f0;
  animation: viewport-pulse 1.4s ease-in-out infinite;
}

.inline-skeleton.wide {
  width: 82px;
}

@keyframes viewport-pulse {
  50% {
    opacity: 0.55;
  }
}

@media (max-width: 620px) {
  .product-columns-heading {
    display: none;
  }
  .b2b-list-product-row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr) auto;
    gap: 6px 0;
    margin: 4px 8px;
    padding: 10px;
  }
  .b2b-list-product-main {
    grid-column: 1 / -1;
    padding-right: 0;
    gap: 10px;
  }
  .b2b-list-product-media {
    gap: 6px;
  }
  .b2b-list-product-column {
    min-height: 36px;
    padding: 0 8px;
    align-items: flex-start;
  }
  .b2b-list-product-quantity {
    border-left: 0;
    padding-left: 0;
  }
  .b2b-list-product-column-label {
    position: static;
    width: auto;
    height: auto;
    clip-path: none;
    color: #64748b;
    font-size: 10px;
  }
  .b2b-list-product-edit {
    grid-column: 3;
  }
}
</style>
