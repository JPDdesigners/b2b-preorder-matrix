<template>
  <div class="matrix-table-outer">
    <CurveModal
      v-model="showCurveModal"
      :store="store"
      :available-sizes="store.data.sizes"
      :size-labels="store.data.size_labels"
      @save="store.setCustomCurve"
    />
    <GestureHelpModal v-model="showHelpModal" />

    <!-- Product identity & toolbar -->
    <div class="table-navigation">
      <div class="product-identity">
        <div class="product-copy">
          <span v-if="store.data.parent_sku" class="product-sku">
            <span class="sr-only">SKU: </span>{{ store.data.parent_sku }}
          </span>
          <h3 v-if="store.data.product_name" class="product-title">
            {{ store.data.product_name }}
          </h3>
        </div>
      </div>

      <div class="navigation-actions">
        <button
          v-if="!readonly"
          class="settings-pill"
          type="button"
          :disabled="pending || store.isSubmitting"
          @click="showCurveModal = true"
        >
          <UiIcon name="sliders" />
          Ρύθμιση καμπύλης
          <span class="curve-chain">{{ stepPillLabel }}</span>
        </button>

        <div v-if="curveToast" class="curve-reset-toast" role="status">
          <span>
            Η αποθηκευμένη καμπύλη δεν ταίριαζε στα μεγέθη. Επανήλθε σε {{ defaultCurveLabel }}.
          </span>
          <button
            type="button"
            aria-label="Κλείσιμο ειδοποίησης"
            @click="dismissCurveToast"
          >
            <UiIcon name="close" />
          </button>
        </div>

        <label class="price-toggle">
          <input v-model="showAllPrices" type="checkbox" />
          <span>Όλες οι τιμές</span>
        </label>

        <button
          v-if="!readonly"
          type="button"
          class="help-pill"
          aria-label="Οδηγίες γρήγορης καταχώρισης"
          title="Οδηγίες γρήγορης καταχώρισης (Tap, Κράτημα, Ροδέλα)"
          @click="showHelpModal = true"
        >
          <UiIcon name="info" />
          <span class="help-pill-label">Οδηγίες</span>
        </button>

        <div v-if="canScrollLeft || canScrollRight" class="scroll-actions">
          <button
            type="button"
            :disabled="!canScrollLeft"
            aria-label="Προηγούμενα μεγέθη"
            @click="scrollTable(-240)"
          >
            <UiIcon name="chevron-left" />
          </button>
          <button
            type="button"
            :disabled="!canScrollRight"
            aria-label="Επόμενα μεγέθη"
            @click="scrollTable(240)"
          >
            <UiIcon name="chevron-right" />
          </button>
        </div>
      </div>
    </div>

    <div ref="grid" class="matrix-grid" :style="{ '--b2b-sticky-top': stickyTop + 'px' }">
      <!-- Docked copy of the size header: sticks under the site/dialog header while colours scroll,
           and leaves together with the last colour row. The in-table header keeps semantics. -->
      <div class="matrix-head-track" aria-hidden="true">
        <div
          ref="headScroller"
          class="matrix-head"
          :class="{ 'is-stuck': headStuck }"
          tabindex="-1"
          @scroll="syncFromHead"
        >
          <table class="matrix-table matrix-table-head" :class="{ compact }">
            <thead>
              <MatrixHeadRow
                :store="store"
                :readonly="readonly"
                :show-row-actions="showRowActions"
                :scrolled="canScrollLeft"
              />
            </thead>
          </table>
        </div>
      </div>

      <div
        ref="tableContainer"
        class="matrix-scroll"
        tabindex="0"
        role="region"
        aria-label="Ποσότητες ανά χρώμα και μέγεθος"
        @scroll="handleScroll"
        @focusin="revealFocusedCell"
      >
        <table class="matrix-table" :class="{ compact }">
          <caption class="sr-only">
            Λίστα παραγγελίας ανά χρώμα και μέγεθος. Οι τιμές είναι ανά τεμάχιο.
          </caption>
          <thead>
            <MatrixHeadRow
              :store="store"
              :readonly="readonly"
              :show-row-actions="showRowActions"
              :scrolled="canScrollLeft"
            />
          </thead>
          <tbody>
            <tr v-for="color in store.data.colors" :key="color">
              <th scope="row" class="sticky-col color-cell" :class="{ scrolled: canScrollLeft }">
                <div class="color-info">
                  <button
                    v-if="colorImage(color)"
                    class="thumbnail"
                    type="button"
                    :aria-label="'Μεγέθυνση: ' + colorName(color)"
                    @click="openColorImage(color)"
                  >
                    <img :src="colorImage(color).img" alt="" loading="lazy" />
                  </button>
                  <div class="color-copy">
                    <span class="color-name" :title="colorName(color)">
                      {{ colorName(color) }}
                    </span>
                    <span v-if="pending" class="price-skeleton" aria-label="Φόρτωση τιμής"></span>
                    <span v-else class="row-price">
                      {{ store.formatMoney(rowPrices[color]) }} <span>/ τεμ.</span>
                    </span>
                  </div>
                </div>
              </th>

              <td v-if="!readonly && showRowActions" class="curve-actions">
                <div class="curve-action-group" :aria-label="'Καμπύλη για ' + colorName(color)">
                  <button
                    type="button"
                    :disabled="pending || store.isSubmitting || !canChangeRow(color, -1)"
                    :aria-label="'Αφαίρεση καμπύλης από ' + colorName(color)"
                    @click="store.incrementRow(color, -1)"
                  >
                    <UiIcon name="minus" />
                  </button>
                  <button
                    type="button"
                    :disabled="pending || store.isSubmitting || !canChangeRow(color, 1)"
                    :aria-label="'Προσθήκη καμπύλης σε ' + colorName(color)"
                    @click="store.incrementRow(color, 1)"
                  >
                    <UiIcon name="plus" />
                  </button>
                </div>
              </td>

              <td v-for="size in store.data.sizes" :key="size" class="quantity-cell">
                <div
                  v-if="cell(color, size)"
                  class="cell-stack"
                  @touchstart.passive="handleCellTouchStart($event, cell(color, size))"
                  @touchmove.passive="handleCellTouchMove($event)"
                  @touchend="handleCellTouchEnd($event, cell(color, size))"
                  @touchcancel="handleCellTouchCancel"
                  @contextmenu="touch.contextMenu"
                >
                  <span v-if="pending" class="cell-skeleton" aria-label="Φόρτωση ποσότητας"></span>
                  <span v-else-if="readonly" class="readonly-qty">
                    {{ store.cart[cell(color, size).id] || 0 }}
                  </span>
                  <input
                    v-else
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    autocomplete="off"
                    placeholder="0"
                    :disabled="pending || store.isSubmitting"
                    :value="store.cart[cell(color, size).id]"
                    :aria-label="
                      colorName(color) +
                      ', ' +
                      store.sizeLabel(size) +
                      ', ποσότητα, ' +
                      store.formatMoney(cell(color, size).price) +
                      ' ανά τεμάχιο'
                    "
                    :title="
                      store.formatMoney(cell(color, size).price) +
                      ' / τεμ. · Ροδέλα: ↑ +1 / ↓ −1'
                    "
                    :class="{
                      filled: store.cart[cell(color, size).id] > 0,
                      changed: isChanged(cell(color, size)),
                    }"
                    @input="editQuantity($event, cell(color, size).id)"
                    @focus="$event.target.select()"
                    @wheel="handleWheel($event, cell(color, size).id)"
                  />
                  <span class="cell-price-slot">
                    <span
                      v-if="!pending && (showAllPrices || isPriceException(color, size))"
                      :class="{ 'price-exception': isPriceException(color, size) }"
                    >
                      {{ store.formatMoney(cell(color, size).price) }}
                    </span>
                  </span>
                </div>
                <span v-else class="unavailable" aria-label="Μη διαθέσιμος συνδυασμός">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import MatrixHeadRow from './MatrixHeadRow.vue';
import { topObstruction, stickyOffsetOverride } from '../stickyOffset.js';
import CurveModal from './CurveModal.vue';
import GestureHelpModal from './GestureHelpModal.vue';
import UiIcon from './UiIcon.vue';
import { createCellTouch } from '../cellTouch.js';
import { changeQuantityWithWheel, createWheelIntent } from '../cellWheel.js';
import { displayColorName } from '../colorNames.js';

const props = defineProps({
  store: { type: Object, required: true },
  readonly: Boolean,
  pending: Boolean,
  compact: Boolean,
  showRowActions: { type: Boolean, default: true },
  // Product page: the window scrolls, so dock the size header under the theme's sticky header.
  pageSticky: Boolean,
});

const showCurveModal = ref(false);
const showHelpModal = ref(false);
const showAllPrices = ref(false);
const tableContainer = ref(null);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const grid = ref(null);
const headScroller = ref(null);
const headStuck = ref(false);
const stickyTop = ref(0);
const curveToast = ref(false);
const defaultCurveLabel = computed(
  () => props.store.data?.sizes?.map(() => '1').join(' · ') || '1'
);

let curveToastTimer;
function dismissCurveToast() {
  curveToast.value = false;
  clearTimeout(curveToastTimer);
}
watch(
  () => props.store.curveResetNotice,
  notice => {
    if (!notice) return;
    props.store.curveResetNotice = false;
    curveToast.value = true;
    clearTimeout(curveToastTimer);
    curveToastTimer = setTimeout(() => {
      curveToast.value = false;
    }, 3000);
  },
  { immediate: true }
);

const cell = (color, size) => props.store.data.matrix[color]?.[size];
const colorName = color => displayColorName(props.store.colorLabel(color));
const rowCells = color => Object.values(props.store.data.matrix[color] || {});
const colorImage = color => rowCells(color).find(item => item.img);
function openColorImage(color) {
  const image = colorImage(color);
  if (image) {
    props.store.openLightbox(
      image.full_img || image.img,
      colorName(color),
      image.image_width,
      image.image_height
    );
  }
}

const rowPrices = computed(() =>
  Object.fromEntries(
    props.store.data.colors.map(color => {
      const counts = new Map();
      rowCells(color).forEach(item => counts.set(item.price, (counts.get(item.price) || 0) + 1));
      const common = [...counts].sort((a, b) => b[1] - a[1] || a[0] - b[0])[0]?.[0] ?? null;
      return [color, common];
    })
  )
);

const isPriceException = (color, size) =>
  props.store.formatMoney(cell(color, size).price) !==
  props.store.formatMoney(rowPrices.value[color]);
const isChanged = item =>
  Number(props.store.cart[item.id] || 0) !==
  Number(props.store.existingQuantities[item.id] || 0);

const stepPillLabel = computed(
  () => props.store.data?.sizes?.map(s => props.store.curveMultiplier(s)).join(' · ') || ''
);

function canChangeRow(color, delta) {
  return props.store.activeCurveSizes.some(size => {
    const item = cell(color, size);
    return (
      item &&
      (delta < 0
        ? Number(props.store.cart[item.id]) > 0
        : Number(props.store.cart[item.id] || 0) < props.store.maxQty)
    );
  });
}

function editQuantity(event, id) {
  if (!/^\d*$/.test(event.target.value)) {
    event.target.value = props.store.cart[id];
    return;
  }
  props.store.setQuantity(id, event.target.value);
  event.target.value = props.store.cart[id];
}

// A scroll that started elsewhere (page, colour column, dialog margins) keeps scrolling over cells.
const wheelIntent = createWheelIntent();
function trackWheel(event) {
  if (!event.target?.closest?.('.cell-stack')) wheelIntent.noteScroll();
}
function handleWheel(event, id) {
  const scrolling = wheelIntent.active();
  if (scrolling) wheelIntent.noteScroll();
  changeQuantityWithWheel(event, id, props.store, {
    disabled:
      props.readonly || props.pending || props.store.loading || props.store.isSubmitting,
    desktop: window.matchMedia('(hover: hover) and (pointer: fine)').matches,
    scrolling,
  });
}

const touch = createCellTouch({
  isDisabled: () =>
    props.readonly || props.pending || props.store.loading || props.store.isSubmitting,
  onTap: item => applyCellTap(item),
  onEdit: input => {
    input?.focus();
    input?.select();
  },
});
const handleCellTouchStart = touch.start;
const handleCellTouchMove = touch.move;
const handleCellTouchEnd = touch.end;
const handleCellTouchCancel = touch.cancel;

function applyCellTap(item) {
  if (props.readonly || props.store.isSubmitting) return;
  const current = Number(props.store.cart[item.id] || 0);
  props.store.setQuantity(item.id, Math.min(props.store.maxQty, current + 1));
}

function handleScroll() {
  const el = tableContainer.value;
  if (!el) return;
  canScrollLeft.value = el.scrollLeft > 2;
  canScrollRight.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 2;
  if (headScroller.value && headScroller.value.scrollLeft !== el.scrollLeft) {
    headScroller.value.scrollLeft = el.scrollLeft;
  }
}
// Dragging the docked header sideways (touch) moves the table too.
function syncFromHead() {
  const head = headScroller.value;
  const body = tableContainer.value;
  if (head && body && body.scrollLeft !== head.scrollLeft) {
    body.scrollLeft = head.scrollLeft;
  }
}

function scrollTable(left) {
  tableContainer.value?.scrollBy({ left, behavior: 'smooth' });
}

// Docking: the copy is sticky inside the grid, so it locks under the top bar exactly when the
// table header reaches it and is released with the last colour row. One scroller per screen:
// the window on the product page, the editor body in My List — never a nested vertical box.
let frame = 0;
function scheduleLayout() {
  if (!frame) frame = requestAnimationFrame(updateLayout);
}
function updateLayout() {
  frame = 0;
  const g = grid.value;
  const head = headScroller.value;
  if (!g || !head) return;
  const gridTop = g.getBoundingClientRect().top;
  if (props.pageSticky && g.getBoundingClientRect().bottom > 0 && gridTop < window.innerHeight) {
    const offset =
      stickyOffsetOverride(g) ?? topObstruction({ root: g.closest('.b2b-matrix-app') || g });
    if (offset !== stickyTop.value) stickyTop.value = offset;
  }
  headStuck.value = head.getBoundingClientRect().top - gridTop > 0.5;
}

function verticalScroller(el) {
  for (let node = el?.parentElement; node && node !== document.body; node = node.parentElement) {
    const overflow = getComputedStyle(node).overflowY;
    if ((overflow === 'auto' || overflow === 'scroll') && node.scrollHeight > node.clientHeight + 1) {
      return node;
    }
  }
  return window;
}

// Keyboard/hold-to-edit focus: keep the cell clear of the docked header and the sticky footer.
function revealFocusedCell(event) {
  if (!(event.target instanceof HTMLInputElement)) return;
  requestAnimationFrame(() => {
    const head = headScroller.value;
    const cellBox = event.target.closest('td') || event.target;
    if (!head || !cellBox.isConnected) return;
    const rect = cellBox.getBoundingClientRect();
    const top = head.getBoundingClientRect().bottom + 4;
    const footer = event.target
      .closest('.b2b-matrix-app')
      ?.querySelector('.matrix-footer')
      ?.getBoundingClientRect();
    const viewportBottom = window.visualViewport?.height ?? window.innerHeight;
    const bottom =
      (footer && footer.top > top && footer.top < viewportBottom ? footer.top : viewportBottom) - 4;
    const delta =
      rect.top < top ? rect.top - top : rect.bottom > bottom ? rect.bottom - bottom : 0;
    if (delta) verticalScroller(tableContainer.value).scrollBy({ top: delta, behavior: 'smooth' });
  });
}

let observer;
onMounted(() => {
  observer = new ResizeObserver(() => {
    handleScroll();
    scheduleLayout();
  });
  observer.observe(tableContainer.value);
  observer.observe(grid.value);
  window.addEventListener('scroll', scheduleLayout, { capture: true, passive: true });
  window.addEventListener('resize', scheduleLayout, { passive: true });
  window.addEventListener('wheel', trackWheel, { capture: true, passive: true });
  handleScroll();
  updateLayout();
});
onUnmounted(() => {
  observer?.disconnect();
  touch.cancel();
  clearTimeout(curveToastTimer);
  cancelAnimationFrame(frame);
  window.removeEventListener('scroll', scheduleLayout, { capture: true });
  window.removeEventListener('resize', scheduleLayout);
  window.removeEventListener('wheel', trackWheel, { capture: true });
});
</script>

<style scoped>
.matrix-table-outer {
  color: #0f172a;
}

.table-navigation {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 20px;
  padding: 16px 24px;
  background: #ffffff;
  border-bottom: 1px solid #e2e8f0;
}

.product-identity {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px 12px;
  min-width: 0;
  flex: 1 1 260px;
}

.product-copy {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 5px;
  min-width: 0;
}

.navigation-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 10px 14px;
  margin-left: auto;
}

.curve-chain {
  font-variant-numeric: tabular-nums;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  background: var(--b2b-primary-soft);
  border-radius: 4px;
  padding: 3px 6px;
  font-size: 11px;
}

.product-title {
  margin: 0;
  font-family: inherit;
  font-size: 20px;
  line-height: 1.3;
  font-weight: 600;
  letter-spacing: -0.35px;
  color: #0f172a;
  overflow-wrap: anywhere;
}

.product-sku {
  display: inline-flex;
  align-items: center;
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  color: #475569;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius-sm, 4px);
  padding: 2px 8px;
  letter-spacing: 0.02em;
}

.scroll-actions {
  display: flex;
  flex-shrink: 0;
  gap: 6px;
}

.scroll-actions button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--b2b-radius, 6px);
  width: 36px;
  height: 36px;
  border: 1px solid #e2e8f0;
  background: #ffffff;
  font-size: 18px;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s ease;
}

.scroll-actions button:hover:not(:disabled) {
  background: #f8fafc;
  border-color: #cbd5e1;
  color: #0f172a;
}

.scroll-actions button:disabled {
  opacity: 0.35;
  cursor: default;
}

.curve-reset-toast {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  max-width: 340px;
  padding: 7px 8px 7px 12px;
  border: 1px solid #fde68a;
  border-radius: var(--b2b-radius, 8px);
  background: #fffbeb;
  color: #92400e;
  font-size: 11px;
  line-height: 1.35;
}

.curve-reset-toast button {
  display: grid;
  place-items: center;
  flex: 0 0 24px;
  width: 24px;
  height: 24px;
  min-height: 24px;
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.curve-reset-toast button :deep(svg) {
  width: 15px;
  height: 15px;
}

.settings-pill,
.help-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 38px;
  padding: 5px 12px;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius, 8px);
  background: #ffffff;
  color: #334155;
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.15s ease;
}

.settings-pill :deep(svg),
.help-pill :deep(svg) {
  width: 15px;
  height: 15px;
  color: #64748b;
}

.settings-pill:hover:not(:disabled),
.help-pill:hover:not(:disabled) {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  border-color: var(--b2b-primary-border);
  background: var(--b2b-primary-soft);
}

.settings-pill:hover:not(:disabled) :deep(svg),
.help-pill:hover:not(:disabled) :deep(svg) {
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.price-toggle {
  display: inline-flex !important;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  min-height: 36px;
  margin: 0 !important;
  padding: 0 !important;
  cursor: pointer;
  font-family: inherit !important;
  font-size: 12px;
  font-weight: 500;
  color: #475569;
  white-space: nowrap;
  user-select: none;
}

.price-toggle input {
  appearance: auto !important;
  accent-color: var(--b2b-focus-ring, var(--b2b-primary));
  width: 16px !important;
  height: 16px !important;
  min-width: 16px !important;
  min-height: 16px !important;
  margin: 0 !important;
  padding: 0 !important;
  flex: 0 0 16px;
  cursor: pointer;
  vertical-align: middle;
}

.price-toggle span {
  display: inline-block;
  line-height: 1.2;
}

/* Horizontal scroll only. Vertical scrolling belongs to one scroller per screen (the page on the
   product page, the editor body in My List), so touch and wheel never get trapped in a nested box. */
.matrix-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-x: contain;
  scroll-padding-left: 215px;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f8fafc;
}

.matrix-scroll::-webkit-scrollbar {
  height: 6px;
}

.matrix-scroll::-webkit-scrollbar-track {
  background: #f8fafc;
}

.matrix-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}

.matrix-scroll::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Docked size header: an aria-hidden copy laid exactly over the real header. It is sticky inside the
   full-height track, so it locks under the top bar when the header reaches it and leaves with the
   last colour row. Pointer events stay on the copy so a tap on it never reaches a cell beneath. */
.matrix-grid {
  position: relative;
}

.matrix-head-track {
  position: absolute;
  inset: 0;
  z-index: 6;
  pointer-events: none;
}

.matrix-head {
  position: sticky;
  top: var(--b2b-sticky-top, 0px);
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  pointer-events: auto;
  background: #f8fafc;
  transition: box-shadow 0.2s ease;
}

.matrix-head::-webkit-scrollbar {
  display: none;
}

.matrix-head.is-stuck {
  box-shadow: 0 10px 14px -10px rgba(15, 23, 42, 0.28);
}

.matrix-table {
  font-family: inherit;
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
  margin: 0;
  font-variant-numeric: tabular-nums;
}

.matrix-table tbody tr {
  transition: background-color 0.12s ease;
}

.matrix-table tbody tr:hover td,
.matrix-table tbody tr:hover th {
  background-color: #f8fafc !important;
}

.matrix-table :deep(th),
.matrix-table td {
  box-sizing: border-box;
  border: 0;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
}

.matrix-table :deep(thead th) {
  height: 46px;
  background: #f8fafc;
  font-weight: 600;
  font-size: 12px;
  color: #475569;
  width: 96px;
  border-bottom: 1px solid #e2e8f0;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  transition: color 0.15s ease;
}

.matrix-table :deep(thead th.curve-heading) {
  width: 120px;
}

.matrix-table :deep(thead th.selected-size) {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-weight: 700;
}

.matrix-table :deep(thead th.excluded-size) {
  color: #94a3b8;
  font-weight: 500;
  opacity: 0.6;
}

.matrix-table :deep(.size-dot) {
  display: inline-block;
  width: 5px;
  height: 5px;
  background: var(--b2b-primary-ink, var(--b2b-primary));
  border-radius: 50%;
  margin-left: 5px;
  vertical-align: middle;
}

.matrix-table :deep(.sticky-col) {
  position: sticky;
  left: 0;
  z-index: 2;
  background: #ffffff;
  width: 215px;
  text-align: left;
  border-right: 1px solid #e2e8f0;
  transition: box-shadow 0.2s ease, background-color 0.12s ease;
}

.matrix-table :deep(thead .sticky-col) {
  background: #f8fafc;
  padding-left: 24px;
  border-bottom: 1px solid #e2e8f0;
}

.matrix-table :deep(thead th.sticky-col) {
  z-index: 4;
}

.matrix-table :deep(.sticky-col.scrolled) {
  box-shadow: 4px 0 10px -2px rgba(0, 0, 0, 0.08);
}

.matrix-table :deep(.color-heading span) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #e2e8f0;
  color: #475569;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  margin-left: 6px;
}

.color-cell {
  padding: 14px 16px 14px 24px;
}

.color-info {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 72px;
}

.thumbnail {
  flex: 0 0 62px;
  width: 62px;
  height: 72px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  border-radius: var(--b2b-radius, 6px);
  padding: 0;
  cursor: zoom-in;
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.thumbnail:hover {
  border-color: var(--b2b-focus-ring, var(--b2b-primary));
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.thumbnail img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.color-copy {
  min-width: 0;
}

.color-name {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.35;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-transform: none !important;
}

.row-price {
  display: block;
  margin-top: 4px;
  color: #334155;
  font-size: 12px;
  font-weight: 600;
}

.row-price span {
  color: #64748b;
  font-size: 11px;
  font-weight: 400;
}

.curve-actions {
  padding: 10px 6px;
  background: #ffffff;
}

.curve-action-group {
  display: flex;
  justify-content: center;
  gap: 5px;
}

.curve-action-group button {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  padding: 0;
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius, 8px);
  background: #ffffff;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  cursor: pointer;
}

.curve-action-group button:last-child {
  background: var(--b2b-primary-soft);
}

.curve-action-group button :deep(svg) {
  width: 20px;
  height: 20px;
}

.quantity-cell {
  padding: 8px 4px;
}

.cell-stack {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  touch-action: manipulation;
  -webkit-touch-callout: none;
}

.cell-stack input {
  box-sizing: border-box;
  width: 74px;
  max-width: 100%;
  height: 48px;
  padding: 0 4px;
  text-align: center;
  border: 1.5px solid #e2e8f0;
  border-radius: var(--b2b-radius-input, 8px);
  font-family: inherit;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  background: #ffffff;
  appearance: textfield;
  -moz-appearance: textfield;
  font-variant-numeric: tabular-nums;
  transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease,
    transform 0.1s ease;
  -webkit-user-select: text;
  user-select: text;
}

.cell-stack input::-webkit-inner-spin-button,
.cell-stack input::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

.cell-stack input::placeholder {
  color: #94a3b8;
  font-weight: 400;
}

.cell-stack input.filled {
  background: var(--b2b-primary-soft);
  border-color: var(--b2b-primary-border);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-weight: 700;
}

.cell-stack input.changed {
  border-color: var(--b2b-focus-ring, var(--b2b-primary));
  box-shadow: inset 0 -2px var(--b2b-focus-ring, var(--b2b-primary));
}

.cell-stack input:focus {
  outline: none;
  border-color: var(--b2b-focus-ring, var(--b2b-primary));
  box-shadow: 0 0 0 3px var(--b2b-primary-soft);
  background: #ffffff;
}

.cell-price-slot {
  height: 18px;
  display: flex;
  align-items: center;
  font-size: 10px;
  color: #64748b;
  font-weight: 500;
  white-space: nowrap;
}

.price-exception {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-weight: 600;
  border-radius: var(--b2b-radius-sm, 4px);
  padding: 1px 5px;
}

.unavailable {
  color: #cbd5e1;
}

.cell-skeleton {
  width: 74px;
  max-width: 100%;
  height: 48px;
  border-radius: var(--b2b-radius-input);
  background: #e2e8f0;
  animation: cell-pulse 1.5s ease infinite alternate;
}

.price-skeleton {
  display: block;
  width: 58px;
  height: 13px;
  margin-top: 4px;
  border-radius: 3px;
  background: #e2e8f0;
  animation: cell-pulse 1.5s ease infinite alternate;
}

@keyframes cell-pulse {
  to {
    opacity: 0.4;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cell-skeleton,
  .price-skeleton {
    animation: none;
  }
}

.readonly-qty {
  height: 48px;
  display: grid;
  place-items: center;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
}

button:disabled {
  opacity: 0.35;
  cursor: default;
}

button:focus-visible,
.price-toggle input:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: 2px;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
}

.compact .color-cell {
  padding-block: 10px;
}

.matrix-table tbody tr:last-child th,
.matrix-table tbody tr:last-child td {
  border-bottom: 0;
}

@media (max-width: 900px) {
  .table-navigation {
    padding: 14px 16px;
  }
  .matrix-table :deep(.sticky-col) {
    width: 190px;
  }
  .matrix-scroll {
    scroll-padding-left: 190px;
  }
  .matrix-table :deep(thead .sticky-col) {
    padding-left: 16px;
  }
  .color-cell {
    padding: 12px 8px 10px 16px;
  }
  .color-info {
    gap: 8px;
    min-height: 60px;
  }
  .thumbnail {
    flex: 0 0 52px;
    width: 52px;
    height: 60px;
  }
  .color-name {
    font-size: 11px;
  }
  .row-price {
    font-size: 11px;
    margin-top: 3px;
  }
  .curve-action-group button {
    width: 46px;
    height: 46px;
  }
  .matrix-table :deep(thead th) {
    width: 85px;
    font-size: 11px;
  }
  .quantity-cell {
    padding: 8px 3px;
  }
  .cell-stack input,
  .cell-skeleton {
    width: 100%;
    max-width: 66px;
    height: 46px;
    font-size: 15px;
  }
}

@media (max-width: 600px) {
  .table-navigation {
    padding: 12px;
  }
  .navigation-actions {
    width: 100%;
    gap: 8px;
  }
  .settings-pill,
  .help-pill {
    padding-inline: 8px;
    font-size: 11px;
  }
  .help-pill-label {
    display: none;
  }
  .curve-chain {
    display: none;
  }
  .matrix-table :deep(.sticky-col) {
    width: 155px;
  }
  .matrix-scroll {
    scroll-padding-left: 155px;
  }
  .color-cell {
    padding: 10px 6px 10px 12px;
  }
  .matrix-table :deep(thead .sticky-col) {
    padding-left: 12px;
  }
  .color-info {
    gap: 6px;
  }
  .thumbnail {
    flex-basis: 46px;
    width: 46px;
    height: 54px;
  }
  .matrix-table :deep(thead th) {
    width: 78px;
  }
  .product-title {
    font-size: 18px;
  }
  .product-sku {
    font-size: 11px;
  }
  .matrix-table :deep(thead th.curve-heading) {
    width: 112px;
  }
  .curve-action-group button {
    width: 44px;
    height: 44px;
  }
}
</style>
