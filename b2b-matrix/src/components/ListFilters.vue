<template>
  <div class="list-filters-shell">
    <div class="products-heading">
      <div class="products-heading-left">
        <div class="section-heading">
          <span class="section-icon">
            <UiIcon name="layers" />
          </span>
          <h2>
            Κωδικοί σχεδίου
            <span class="count-pill">{{ totalCount }}</span>
          </h2>
        </div>

        <div
          v-if="totalCount && roots.length"
          class="root-tabs"
          role="group"
          aria-label="Κατηγορίες ρίζας"
        >
          <button
            type="button"
            :class="{ active: !rootId }"
            :aria-pressed="!rootId"
            @click="selectRoot('')"
          >
            Όλα
          </button>
          <button
            v-for="root in roots"
            :key="root.id"
            type="button"
            :class="{ active: rootId === String(root.id) }"
            :aria-pressed="rootId === String(root.id)"
            @click="selectRoot(rootId === String(root.id) ? '' : String(root.id))"
          >
            {{ root.name }}
          </button>
        </div>
      </div>

      <B2BButton
        v-if="totalCount"
        class="empty-list-button"
        variant="outline"
        size="sm"
        icon="trash"
        :disabled="locked"
        @click="$emit('empty')"
      >
        Άδειασμα λίστας
      </B2BButton>
    </div>

    <div v-if="totalCount" class="list-filters">
      <div class="filter-toolbar">
        <div class="filter-controls">
          <div class="list-search-field">
            <UiIcon name="search" />
            <input
              :value="filter"
              class="b2b-field filter-field"
              type="search"
              aria-label="Αναζήτηση στη λίστα"
              placeholder="Αναζήτηση στη λίστα…"
              @input="$emit('update:filter', $event.target.value)"
            />
          </div>

          <div class="category-select">
            <select
              :value="category"
              class="b2b-field filter-field"
              aria-label="Φίλτρο υποκατηγορίας"
              @change="$emit('update:category', $event.target.value)"
            >
              <option value="">Όλες οι υποκατηγορίες</option>
              <option v-for="c in categories" :key="c.id" :value="String(c.id)">
                {{ !rootId && c.root_name ? c.root_name + ' / ' + c.name : c.name }}
              </option>
            </select>
            <UiIcon name="chevron-down" />
          </div>
        </div>

        <div class="filter-subtotal" role="status">
          <div class="subtotal-meta">
            <span class="subtotal-label">Υποσύνολο προβολής</span>
            <div class="subtotal-counts">
              <span>
                {{ filteredCount }} {{ filteredCount === 1 ? 'κωδικός' : 'κωδικοί' }}
              </span>
              <span class="subtotal-dot">·</span>
              <span>{{ filteredTotals.qty }} τεμάχια</span>
            </div>
          </div>
          <strong v-if="loading" class="inline-skeleton wide"></strong>
          <strong v-else class="subtotal-amount">
            {{ filteredTotals.unpriced ? '—' : money(filteredTotals.value) }}
          </strong>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';

defineProps({
  roots: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  rootId: { type: String, default: '' },
  category: { type: String, default: '' },
  filter: { type: String, default: '' },
  totalCount: { type: Number, default: 0 },
  filteredCount: { type: Number, default: 0 },
  filteredTotals: { type: Object, required: true },
  loading: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
  money: { type: Function, required: true },
});

const emit = defineEmits([
  'update:rootId',
  'update:category',
  'update:filter',
  'empty',
]);

function selectRoot(nextRootId) {
  emit('update:rootId', nextRootId);
  emit('update:category', '');
}
</script>

<style scoped>
.products-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 16px;
  padding: 12px 18px;
  background: #ffffff;
  border-bottom: 1px solid #f1f5f9;
}

.products-heading-left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px 16px;
  min-width: 0;
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.section-icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex: 0 0 28px;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius-sm, 6px);
}

.section-icon :deep(svg) {
  width: 15px;
  height: 15px;
}

.section-heading h2 {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0 !important;
  color: #0f172a;
  font-size: 14px;
  line-height: 1.3;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.count-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 22px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: 11px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.root-tabs {
  display: flex;
  align-items: center;
  gap: 3px;
  width: max-content;
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
  scrollbar-width: thin;
  padding: 3px;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius, 8px);
  background: #f8fafc;
}

.root-tabs button {
  flex: 0 0 auto;
  min-height: 30px;
  padding: 4px 11px;
  border: 1px solid transparent;
  border-radius: var(--b2b-radius-sm, 6px);
  background: transparent;
  color: #475569;
  font: inherit;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.12s ease;
}

.root-tabs button:hover:not(.active) {
  color: #0f172a;
  background: #ffffff;
}

.root-tabs button.active {
  border-color: var(--b2b-primary-border);
  background: #ffffff;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}

.root-tabs button:focus-visible {
  outline: 2px solid var(--b2b-focus-ring, var(--b2b-primary));
  outline-offset: 1px;
}

.empty-list-button {
  flex-shrink: 0;
  color: #475569 !important;
  border-color: #e2e8f0 !important;
  background: #ffffff !important;
}

.empty-list-button:is(:hover, :focus-visible):not(:disabled):not(.is-disabled) {
  color: #dc2626 !important;
  border-color: #fecaca !important;
  background: #fff1f2 !important;
}

.list-filters {
  padding: 10px 18px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.filter-toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 10px 14px;
}

.filter-controls {
  display: grid;
  grid-template-columns: minmax(180px, 1.15fr) minmax(160px, 0.85fr);
  gap: 8px;
  min-width: 0;
}

.list-search-field,
.category-select {
  position: relative;
  min-width: 0;
}

.filter-field {
  min-height: 38px !important;
  padding: 7px 12px !important;
  font-size: 12.5px !important;
  border-color: #cbd5e1 !important;
  background: #ffffff !important;
}

.list-search-field > :deep(svg) {
  position: absolute;
  top: 50%;
  left: 11px;
  transform: translateY(-50%);
  width: 15px;
  height: 15px;
  color: #94a3b8;
  pointer-events: none;
}

.list-search-field input {
  padding-left: 34px !important;
}

.category-select select {
  width: 100%;
  padding-right: 32px !important;
  appearance: none !important;
  cursor: pointer;
}

.category-select > :deep(svg) {
  position: absolute;
  top: 50%;
  right: 10px;
  transform: translateY(-50%);
  width: 15px;
  height: 15px;
  color: #64748b;
  pointer-events: none;
}

.filter-subtotal {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  min-height: 38px;
  padding: 6px 12px;
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius, 8px);
  background: var(--b2b-primary-soft);
}

.subtotal-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.subtotal-label {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  line-height: 1.1;
}

.subtotal-counts {
  display: flex;
  align-items: center;
  gap: 5px;
  color: #475569;
  font-size: 11px;
  font-weight: 500;
  white-space: nowrap;
}

.subtotal-dot {
  color: #94a3b8;
}

.subtotal-amount {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 16px;
  font-weight: 700;
  line-height: 1.1;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.inline-skeleton {
  display: inline-block;
  width: 42px;
  height: 13px;
  vertical-align: middle;
  border-radius: 4px;
  background: #e2e8f0;
  animation: filter-pulse 1.4s ease-in-out infinite;
}

.inline-skeleton.wide {
  width: 78px;
}

@keyframes filter-pulse {
  50% {
    opacity: 0.55;
  }
}

@media (max-width: 760px) {
  .filter-toolbar {
    grid-template-columns: 1fr;
  }
  .filter-controls {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 620px) {
  .products-heading {
    padding: 10px 14px;
  }
  .list-filters {
    padding: 10px 14px;
  }
  .filter-controls {
    grid-template-columns: 1fr;
  }
  .root-tabs {
    width: 100%;
  }
}
</style>
