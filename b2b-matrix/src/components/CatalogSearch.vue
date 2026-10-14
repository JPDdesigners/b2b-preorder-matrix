<template>
  <section
    ref="quickAdd"
    class="quick-add"
    aria-label="Προσθήκη κωδικού"
    @focusout="catalogFocusOut"
  >
    <div ref="catalogSearchRow" class="catalog-search-row">
      <div class="catalog-search-field">
        <UiIcon name="search" />
        <input
          ref="inputEl"
          v-model="catalogQuery"
          class="b2b-field catalog-input"
          type="search"
          aria-label="Αναζήτηση στον κατάλογο"
          autocomplete="off"
          enterkeyhint="go"
          :inputmode="numeric ? 'numeric' : 'search'"
          :disabled="locked"
          placeholder="Προσθήκη κωδικού (SKU ή όνομα)…"
          @input="searchCatalog()"
          @focus="showCatalogResults"
          @click="showCatalogResults"
          @keydown.enter="enterCatalog"
          @keydown.esc.prevent="hideCatalogResults"
        />
      </div>
      <button
        type="button"
        class="keyboard-switch"
        :aria-pressed="numeric"
        :disabled="locked"
        :aria-label="
          numeric
            ? 'Αλλαγή σε αλφαβητικό πληκτρολόγιο'
            : 'Αλλαγή σε αριθμητικό πληκτρολόγιο'
        "
        @click="numeric = !numeric"
        @keydown.tab="tabIntoCatalog"
      >
        <UiIcon name="keyboard" />
        <span>{{ numeric ? '123' : 'ABC' }}</span>
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="catalogResultsOpen && (searching || searchError || results.length || searched)"
        ref="catalogPanel"
        class="b2b-ui catalog-search-results"
        :class="'catalog-panel-' + catalogPlacement"
        :style="{ ...themeStyle, ...catalogPanelStyle }"
        :popover="catalogUsesPopover ? 'manual' : undefined"
        role="region"
        aria-label="Αποτελέσματα καταλόγου"
        @mousedown.prevent
        @focusout="catalogFocusOut"
        @keydown.esc.prevent="hideCatalogResults"
      >
        <div class="catalog-results-heading">
          <span>
            Αποτελέσματα καταλόγου
            <span v-if="!searching && results.length" class="catalog-result-count">
              {{ results.length }}
            </span>
          </span>
          <button
            type="button"
            aria-label="Κλείσιμο αποτελεσμάτων"
            @click="hideCatalogResults"
          >
            <UiIcon name="close" />
          </button>
        </div>

        <div class="catalog-results-scroll">
          <p v-if="searching" class="catalog-search-status" role="status">
            Αναζήτηση…
          </p>
          <p
            v-else-if="searchError"
            class="catalog-search-status catalog-search-error"
            role="alert"
          >
            {{ searchError }}
          </p>
          <template v-else-if="results.length">
            <p v-if="catalogEnterHint" class="catalog-search-status" role="status">
              {{ catalogEnterHint }}
            </p>
            <button
              v-for="product in catalogResults"
              :key="product.id"
              type="button"
              class="b2b-result"
              :disabled="locked"
              @click="selectProduct(product.id)"
            >
              <img
                v-if="product.image"
                :src="product.image"
                alt=""
                loading="lazy"
                decoding="async"
                class="result-thumb"
              />
              <span class="result-body">
                <span class="result-meta">
                  <strong class="result-sku">{{ product.sku }}</strong>
                  <template v-if="product.brand_name"> · {{ product.brand_name }}</template>
                </span>
                <span
                  v-if="product.matched_sku && product.matched_sku !== product.sku"
                  class="catalog-variation-sku"
                >
                  SKU παραλλαγής: {{ product.matched_sku }}
                </span>
                <span class="result-name">{{ product.name }}</span>
                <span v-if="product.in_list" class="catalog-in-list">Ήδη στη λίστα</span>
              </span>
              <span class="result-action-icon">
                <UiIcon :name="product.in_list ? 'edit' : 'plus'" />
              </span>
            </button>
          </template>
          <p v-else class="catalog-search-status" role="status">
            Δεν βρέθηκαν κωδικοί.
          </p>
        </div>

        <p v-if="!searching && results.length === 8" class="catalog-results-more">
          Γράψτε περισσότερα για πιο συγκεκριμένα αποτελέσματα.
        </p>
      </div>
    </Teleport>
  </section>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import UiIcon from './UiIcon.vue';
import {
  exactCatalogProduct,
  canSearchCatalog,
  catalogWithMembership,
  catalogPanelPlacement,
  CATALOG_SEARCH_DELAY,
} from '../catalogSearch.js';

const props = defineProps({
  client: { type: Function, required: true },
  listedProductIds: { type: Object, required: true },
  locked: { type: Boolean, default: false },
  matrixOpen: { type: Boolean, default: false },
  themeStyle: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['select']);

const keyboardKey = 'b2b_list_numeric_keyboard';
function savedKeyboardMode() {
  try {
    return localStorage.getItem(keyboardKey) === '1';
  } catch {
    return false;
  }
}

const catalogQuery = ref('');
const results = ref([]);
const searching = ref(false);
const searched = ref(false);
const searchError = ref('');
const numeric = ref(savedKeyboardMode());
const quickAdd = ref(null);
const inputEl = ref(null);
const catalogResultsOpen = ref(false);
const catalogPanel = ref(null);
const catalogSearchRow = ref(null);
const catalogPanelStyle = ref({});
const catalogPlacement = ref('below');
const catalogEnterHint = ref('');

const catalogResults = computed(() =>
  catalogWithMembership(results.value, props.listedProductIds)
);

const catalogUsesPopover =
  typeof HTMLElement !== 'undefined' && 'showPopover' in HTMLElement.prototype;

watch(
  catalogPanel,
  element => {
    if (!element) return;
    positionCatalogResults();
    if (catalogUsesPopover) {
      try {
        element.showPopover({ source: inputEl.value });
      } catch {
        element.removeAttribute('popover');
      }
    }
  },
  { flush: 'post' }
);

function positionCatalogResults() {
  if (!catalogResultsOpen.value || !catalogSearchRow.value) return;
  const view = window.visualViewport;
  const viewport = {
    left: view?.offsetLeft || 0,
    top: view?.offsetTop || 0,
    width: view?.width || window.innerWidth,
    height: view?.height || window.innerHeight,
  };
  const keyboardOpen = viewport.height < window.innerHeight - 120;
  const preferSide =
    window.innerWidth <= 1024 ||
    window.matchMedia('(pointer: coarse)').matches ||
    keyboardOpen;
  const placement = catalogPanelPlacement(
    catalogSearchRow.value.getBoundingClientRect(),
    viewport,
    preferSide
  );
  catalogPlacement.value = placement.mode;
  catalogPanelStyle.value = {
    left: placement.left + 'px',
    top: placement.top + 'px',
    width: placement.width + 'px',
    maxHeight: placement.maxHeight + 'px',
  };
}

function showCatalogResults() {
  catalogResultsOpen.value = canSearchCatalog(catalogQuery.value);
  positionCatalogResults();
}

watch(catalogResultsOpen, async open => {
  if (open) {
    await nextTick();
    positionCatalogResults();
  }
});

let searchTimer;
let searchController;
let searchVersion = 0;
let searchedCatalogQuery = '';
let pendingCatalogEnter = false;

function hideCatalogResults() {
  catalogResultsOpen.value = false;
  pendingCatalogEnter = false;
}

function insideCatalog(target) {
  return (
    !!target &&
    (quickAdd.value?.contains(target) || catalogPanel.value?.contains(target))
  );
}

function catalogFocusOut(event) {
  if (!insideCatalog(event.relatedTarget)) hideCatalogResults();
}

function dismissCatalogResults(event) {
  if (!insideCatalog(event.target)) hideCatalogResults();
}

function tabIntoCatalog(event) {
  if (event.shiftKey || catalogPanel.value?.hasAttribute('popover')) return;
  const button = catalogPanel.value?.querySelector('button');
  if (button) {
    event.preventDefault();
    button.focus();
  }
}

watch(numeric, value => {
  try {
    localStorage.setItem(keyboardKey, value ? '1' : '0');
  } catch {
    /* Browser storage may be unavailable. */
  }
});

function selectProduct(id) {
  hideCatalogResults();
  emit('select', id);
}

function openExactCatalog(query) {
  const product = exactCatalogProduct(results.value, query);
  if (product) selectProduct(product.id);
  else catalogEnterHint.value = 'Επιλέξτε κωδικό από τα αποτελέσματα.';
}

function enterCatalog(event) {
  if (event.isComposing || event.repeat || props.locked || props.matrixOpen) return;
  event.preventDefault();
  const query = catalogQuery.value.trim();
  if (!canSearchCatalog(query) || pendingCatalogEnter) return;
  catalogResultsOpen.value = true;
  if (searched.value && !searching.value && searchedCatalogQuery === query) {
    openExactCatalog(query);
  } else {
    searchCatalog(true, true);
  }
}

function searchCatalog(immediate = false, openOnComplete = false) {
  clearTimeout(searchTimer);
  searchController?.abort();
  const version = ++searchVersion;
  const query = catalogQuery.value.trim();
  pendingCatalogEnter = openOnComplete;
  catalogEnterHint.value = '';
  results.value = [];
  searched.value = false;
  searchError.value = '';
  searching.value = false;
  catalogResultsOpen.value = canSearchCatalog(query);
  if (!catalogResultsOpen.value) return;
  positionCatalogResults();
  searching.value = true;

  const runSearch = async () => {
    searchController = new AbortController();
    try {
      const data = await props.client().read(
        'search-products',
        { q: query },
        searchController.signal
      );
      if (version !== searchVersion) return;
      results.value = data;
      searched.value = true;
      searchedCatalogQuery = query;
      const shouldOpen = pendingCatalogEnter;
      pendingCatalogEnter = false;
      if (
        shouldOpen &&
        query === catalogQuery.value.trim() &&
        !props.locked &&
        !props.matrixOpen
      ) {
        openExactCatalog(query);
      }
    } catch (e) {
      if (version === searchVersion) {
        pendingCatalogEnter = false;
        if (e.name !== 'AbortError') searchError.value = e.message;
      }
    } finally {
      if (version === searchVersion) searching.value = false;
    }
  };

  if (immediate) runSearch();
  else searchTimer = setTimeout(runSearch, CATALOG_SEARCH_DELAY);
}

function focusInput() {
  const input = inputEl.value;
  if (!input || props.matrixOpen) return;
  input.focus({ preventScroll: true });
  input.setSelectionRange(input.value.length, input.value.length);
  hideCatalogResults();
}

defineExpose({ focusInput, hideCatalogResults });

onMounted(() => {
  window.addEventListener('resize', positionCatalogResults);
  window.addEventListener('scroll', positionCatalogResults, true);
  window.visualViewport?.addEventListener('resize', positionCatalogResults);
  window.visualViewport?.addEventListener('scroll', positionCatalogResults);
  document.addEventListener('pointerdown', dismissCatalogResults);
});

onUnmounted(() => {
  clearTimeout(searchTimer);
  searchController?.abort();
  ++searchVersion;
  pendingCatalogEnter = false;
  document.removeEventListener('pointerdown', dismissCatalogResults);
  window.removeEventListener('resize', positionCatalogResults);
  window.removeEventListener('scroll', positionCatalogResults, true);
  window.visualViewport?.removeEventListener('resize', positionCatalogResults);
  window.visualViewport?.removeEventListener('scroll', positionCatalogResults);
});
</script>

<style scoped>
.quick-add {
  position: relative;
  min-width: 0;
}

.catalog-search-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px;
}

.catalog-search-field {
  position: relative;
  min-width: 0;
}

.catalog-search-field > :deep(svg) {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  color: #64748b;
  pointer-events: none;
}

.catalog-input {
  min-height: 40px !important;
  padding: 8px 12px 8px 36px !important;
  font-size: 13px !important;
  border-color: #cbd5e1 !important;
  background: #f8fafc !important;
  transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}

.catalog-input:focus {
  background: #ffffff !important;
  border-color: var(--b2b-focus-ring, var(--b2b-primary)) !important;
  box-shadow: 0 0 0 3px var(--b2b-primary-soft) !important;
  outline: none !important;
}

.keyboard-switch {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-width: 78px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #cbd5e1;
  border-radius: var(--b2b-radius, 8px);
  background: #ffffff;
  color: #334155;
  font: inherit;
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.keyboard-switch:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #94a3b8;
}

.keyboard-switch[aria-pressed='true'] {
  border-color: var(--b2b-primary-border);
  background: var(--b2b-primary-soft);
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.keyboard-switch :deep(svg) {
  width: 16px;
  height: 16px;
}

.keyboard-switch:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: 2px;
}

.keyboard-switch:disabled {
  opacity: 0.5;
  cursor: default;
}

.catalog-search-results {
  position: fixed;
  inset: auto;
  margin: 0;
  z-index: 2147483647;
  display: flex;
  flex-direction: column;
  padding: 6px;
  box-sizing: border-box;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius-lg, 12px);
  background: #ffffff;
  box-shadow: 0 16px 40px -8px rgba(15, 23, 42, 0.2), 0 4px 12px -2px rgba(15, 23, 42, 0.08);
}

.catalog-results-scroll {
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.catalog-results-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  flex: 0 0 auto;
  padding: 4px 6px 6px 10px;
  border-bottom: 1px solid #f1f5f9;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.catalog-results-heading > button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  margin: 0;
  border: 0;
  border-radius: var(--b2b-radius-sm, 6px);
  color: #64748b;
  background: transparent;
  cursor: pointer;
}

.catalog-results-heading > button:hover {
  color: #0f172a;
  background: #f1f5f9;
}

.catalog-result-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  margin-left: 4px;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--b2b-primary-soft);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 11px;
}

.catalog-results-more {
  flex: 0 0 auto;
  margin: 0 !important;
  padding: 8px 10px;
  border-top: 1px solid #f1f5f9;
  color: #64748b;
  font-size: 11px;
  line-height: 1.4;
}

.b2b-result {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 12px;
  padding: 8px 10px;
  margin: 2px 0;
  border: 0;
  border-radius: var(--b2b-radius, 8px);
  color: #0f172a;
  background: transparent;
  text-align: left;
  font-size: 13px;
  line-height: 1.4;
  cursor: pointer;
  box-shadow: none;
  transition: background-color 0.12s ease;
}

.b2b-result:hover,
.b2b-result:focus-visible {
  background: var(--b2b-primary-soft);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  outline: none;
}

.result-thumb {
  width: 40px;
  height: 48px;
  flex: 0 0 40px;
  object-fit: cover;
  border-radius: var(--b2b-radius-sm, 6px);
  border: 1px solid #e2e8f0;
  background: #f8fafc;
}

.result-body {
  min-width: 0;
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.result-meta {
  display: block;
  font-size: 11px;
  color: #64748b;
}

.result-sku {
  color: #334155;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.result-name {
  display: block;
  font-weight: 600;
  color: #0f172a;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.result-action-icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  border-radius: var(--b2b-radius-sm, 6px);
  background: #f8fafc;
  color: #475569;
}

.b2b-result:hover .result-action-icon {
  background: #ffffff;
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.catalog-search-status {
  margin: 0 !important;
  padding: 14px 12px;
  color: #64748b;
  font-size: 12px;
}

.catalog-search-error {
  color: #be123c;
}

.catalog-in-list {
  display: inline-flex;
  align-self: flex-start;
  margin-top: 2px;
  padding: 1px 6px;
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius-sm, 4px);
  background: var(--b2b-primary-soft);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 10px;
  line-height: 1.4;
  font-weight: 600;
}

.catalog-variation-sku {
  display: block;
  color: #64748b;
  font-size: 11px;
}

@media (max-width: 760px) {
  .catalog-search-results {
    left: 0;
  }
}
</style>
