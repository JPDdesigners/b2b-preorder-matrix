<template>
  <main class="b2b-ui b2b-list" :style="themeStyle" :aria-busy="loading">
    <B2BCard class="list-top-card">
      <header class="list-hero">
        <div class="list-hero-icon">
          <UiIcon name="clipboard-check" />
        </div>
        <div class="list-hero-copy">
          <div class="list-hero-title-row">
            <h1>Η λίστα μου</h1>
            <span class="list-eyebrow">
              ΠΡΟΠΑΡΑΓΓΕΛΙΕΣ <template v-if="payload.season">· {{ payload.season }}</template>
            </span>
          </div>
          <p v-if="loaded && !loginRequired && items.length" class="list-hero-kpis">
            <span><strong>{{ items.length }}</strong> κωδικοί</span>
            <span class="kpi-sep">·</span>
            <span><strong>{{ payload.totalQty || 0 }}</strong> τεμ.</span>
            <span class="kpi-sep">·</span>
            <span class="kpi-val">{{ hasUnpriced ? '—' : money(payload.totalVal || 0) }}</span>
          </p>
          <p v-else>
            Επιλέξτε κωδικούς και ελέγξτε τις ποσότητες πριν την υποβολή.
          </p>
        </div>
      </header>

      <CatalogSearch
        v-if="loaded && !loginRequired"
        ref="catalogSearch"
        :client="client"
        :listed-product-ids="listedProductIds"
        :locked="locked"
        :matrix-open="matrixOpen"
        :theme-style="themeStyle"
        @select="id => openMatrix(id, true)"
      />
      <div
        v-else-if="!loginRequired"
        class="quick-add-loading skeleton"
        aria-label="Φόρτωση αναζήτησης"
      ></div>
    </B2BCard>

    <div
      v-if="error"
      class="b2b-u-mb-5 b2b-u-rounded-b2b b2b-u-border b2b-u-border-rose-200 b2b-u-bg-rose-50 b2b-u-p-4 b2b-u-text-rose-800"
      role="alert"
    >
      {{ error }}
      <B2BButton v-if="!loginRequired" variant="outline" size="sm" @click="load">
        Νέα προσπάθεια
      </B2BButton>
    </div>

    <B2BCard v-if="loginRequired" class="b2b-u-p-8 b2b-u-text-center">
      <h2 class="b2b-u-text-lg b2b-u-font-semibold">
        Συνδεθείτε για να δείτε τη λίστα σας
      </h2>
      <p class="b2b-u-my-3 b2b-u-text-zinc-500">
        Οι αποθηκευμένες ποσότητες ανήκουν στον λογαριασμό σας.
      </p>
      <a :href="config.loginUrl" class="b2b-link">Σύνδεση στον λογαριασμό</a>
    </B2BCard>

    <div
      v-else-if="loading && !loaded"
      class="list-loading"
      role="status"
      aria-label="Φόρτωση λίστας"
    >
      <div class="loading-main">
        <div class="loading-filter skeleton"></div>
        <div v-for="i in 5" :key="i" class="loading-product skeleton">
          <span></span><span></span><span></span>
        </div>
      </div>
      <div class="loading-aside skeleton"></div>
    </div>

    <template v-else-if="loaded">
      <div
        v-if="activeBatch"
        class="b2b-u-mb-5 b2b-u-flex b2b-u-flex-wrap b2b-u-items-center b2b-u-justify-between b2b-u-gap-3 b2b-u-rounded-b2b b2b-u-border b2b-u-border-amber-200 b2b-u-bg-amber-50 b2b-u-p-4"
      >
        <div>
          <strong>Υπάρχει καταχώριση σε εξέλιξη</strong>
          <p class="b2b-u-text-xs b2b-u-mt-1">
            Συνεχίστε ή ακυρώστε την καταχώριση για να επεξεργαστείτε τη λίστα.
          </p>
        </div>
        <div class="b2b-u-flex b2b-u-flex-wrap b2b-u-gap-2">
          <B2BButton variant="outline" :disabled="busy" @click="confirmCancel">
            Ακύρωση καταχώρισης
          </B2BButton>
          <B2BButton :disabled="busy" @click="submit">Συνέχιση</B2BButton>
        </div>
      </div>

      <div class="b2b-u-grid b2b-u-items-start b2b-u-gap-5 list-content-grid">
        <div class="b2b-u-min-w-0">
          <B2BCard class="product-list-card">
            <ListFilters
              v-model:root-id="rootId"
              v-model:category="category"
              v-model:filter="filter"
              :roots="roots"
              :categories="categories"
              :total-count="items.length"
              :filtered-count="filtered.length"
              :filtered-totals="filteredTotals"
              :loading="loading"
              :locked="locked"
              :money="money"
              @empty="confirmEmpty"
            />

            <ProductListViewport
              :items="items"
              :filtered="filtered"
              :loading="loading"
              :locked="locked"
              :money="money"
              :reset-key="filterResetKey"
              @edit="openMatrix"
              @remove="confirmRemove"
              @clear-filters="clearFilters"
            />
          </B2BCard>
        </div>

        <OrderSummarySidebar
          v-model:note="note"
          :customer-info="payload.customerInfo"
          :items-count="items.length"
          :total-qty="Number(payload.totalQty) || 0"
          :total-val="Number(payload.totalVal) || 0"
          :loading="loading"
          :locked="locked"
          :has-unpriced="hasUnpriced"
          :has-invalid="hasInvalid"
          :note-id="noteId"
          :note-state="noteState"
          :note-status-text="noteStatusText"
          :channel-selection="channelSelection"
          :active-batch="activeBatch"
          :money="money"
          @save-note="saveNote"
          @resolve-note="resolveNote"
          @submit="confirmSubmit"
        />
      </div>
    </template>

    <MatrixModal
      v-if="matrixOpen"
      :store="matrix"
      @close="closeMatrix"
      @saved="matrixSaved"
    />

    <DialogModal
      :open="removalNoticeOpen"
      label="Ακυρωμένα είδη"
      @close="closeRemovalNotice"
    >
      <section class="removal-notice">
        <h2>Ακυρωμένα είδη</h2>
        <p>
          Οι παρακάτω κωδικοί ή παραλλαγές έχουν ακυρωθεί και αφαιρέθηκαν από τη λίστα σας.
        </p>
        <ul class="removal-notice-items">
          <li v-for="item in payload.removedItems" :key="item.id">
            <strong v-if="item.sku">{{ item.sku }}</strong>
            <span>{{ item.name }}</span>
            <template v-if="item.partial">
              <small>
                Αφαιρέθηκαν μόνο οι ακυρωμένες παραλλαγές. Οι υπόλοιπες επιλογές σας
                παραμένουν στη λίστα.
              </small>
              <ul>
                <li v-for="variation in item.variations" :key="variation.id">
                  {{ variation.sku || variation.name }}
                </li>
              </ul>
            </template>
          </li>
        </ul>
        <footer>
          <B2BButton size="lg" autofocus @click="closeRemovalNotice">
            Ενημερώθηκα
          </B2BButton>
        </footer>
      </section>
    </DialogModal>

    <DialogModal
      :open="!!confirmation"
      :label="confirmation?.title || 'Επιβεβαίωση'"
      @close="!busy && (confirmation = null)"
    >
      <h2 class="b2b-u-text-lg b2b-u-font-semibold">{{ confirmation?.title }}</h2>
      <p class="b2b-u-my-4 b2b-u-text-sm b2b-u-text-zinc-500">
        {{ confirmation?.message }}
      </p>
      <div class="b2b-u-flex b2b-u-justify-end b2b-u-gap-2">
        <B2BButton variant="outline" :disabled="busy" @click="confirmation = null">
          Πίσω
        </B2BButton>
        <B2BButton
          :variant="confirmation?.danger ? 'danger' : 'primary'"
          :loading="busy"
          @click="runConfirmation"
        >
          {{ confirmation?.label || 'Επιβεβαίωση' }}
        </B2BButton>
      </div>
    </DialogModal>

    <EmptyListDialog
      :open="emptyOpen"
      :busy="emptying"
      :item-count="items.length"
      :total-qty="Number(payload.totalQty) || 0"
      :total-value="hasUnpriced ? '—' : money(payload.totalVal || 0)"
      :has-note="!!String(note || '').trim()"
      @close="emptyOpen = false"
      @confirm="emptyList"
    />

    <SubmissionProgress
      :open="progressOpen"
      :submitting="submitting"
      :redirecting="redirecting"
      :finalizing="finalizing"
      :submit-error="submitError"
      :submission-stages="submissionStages"
      :submission-stage="submissionStage"
      :progress="progress"
      :progress-percent="progressPercent"
      @close="progressOpen = false"
      @retry="submit"
    />
  </main>
</template>

<script setup>
import { ref, computed, watch, provide, nextTick, onMounted, onUnmounted, useId } from 'vue';
import { orderChannelSelection } from '../orderChannel.js';
import { createDraftNote } from '../draftNote.js';
import { applySavedListPatch, hasConfirmedListQuote } from '../listState.js';
import B2BButton from './ui/B2BButton.vue';
import B2BCard from './ui/B2BCard.vue';
import UiIcon from './UiIcon.vue';
import DialogModal from './DialogModal.vue';
import MatrixModal from './MatrixModal.vue';
import EmptyListDialog from './EmptyListDialog.vue';
import CatalogSearch from './CatalogSearch.vue';
import ListFilters from './ListFilters.vue';
import ProductListViewport from './ProductListViewport.vue';
import OrderSummarySidebar from './OrderSummarySidebar.vue';
import SubmissionProgress from './SubmissionProgress.vue';
import { EMPTY_CONFIRM_WORD } from '../emptyConfirm.js';
import { appearanceStyle, THEME_KEY, SYSTEM_FONT } from '../theme.js';
import { useMatrixStore } from '../stores/matrixStore.js';
import { createListApi } from '../listApi.js';
import { rootOptions, categoryOptions, filterListItems, listTotals } from '../listView.js';

const props = defineProps({
  container: { type: Object, required: true },
});

let config = {};
try {
  config = JSON.parse(props.container.dataset.b2bConfig || '{}');
} catch {
  /* load displays a connection error */
}

const noteId = useId();
const payload = ref({});
const loaded = ref(false);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const loginRequired = ref(false);
const font = ref(SYSTEM_FONT);
const themeStyle = computed(() => appearanceStyle(payload.value.appearance, font.value));
provide(THEME_KEY, themeStyle);

const items = computed(() => payload.value.items || []);
const activeBatch = computed(() => payload.value.activeBatch);
const resolvingNote = ref(false);
const progress = ref(null);
const progressOpen = ref(false);
const submitting = ref(false);
const redirecting = ref(false);
const submitError = ref('');
const finalizing = ref(false);
const submissionStages = [
  'Προετοιμασία',
  'Καταχώριση γραμμών',
  'Σύνολα και ολοκλήρωση',
  'Μετάβαση',
];
const submissionStage = ref(0);

const locked = computed(
  () =>
    busy.value ||
    loading.value ||
    resolvingNote.value ||
    redirecting.value ||
    !!activeBatch.value
);

const hasUnpriced = computed(() => items.value.some(i => i.unpriced));
const hasInvalid = computed(
  () =>
    !hasConfirmedListQuote(payload.value) ||
    items.value.some(i => i.unavailable || i.unpriced)
);

const rootId = ref('');
const category = ref('');
const filter = ref('');

function clearFilters() {
  filter.value = '';
  rootId.value = '';
  category.value = '';
}

const filterResetKey = computed(
  () => `${rootId.value}|${category.value}|${filter.value}|${items.value.length}`
);

let api;
function client() {
  return (api ||= createListApi(config.ajaxUrl));
}

const noteState = ref({ note: '', dirty: false, status: 'loading', error: '' });
const noteDraft = createDraftNote({
  read: async () => {
    try {
      return (await client().read('draft-note')).draft;
    } catch (e) {
      showError(e);
      throw e;
    }
  },
  write: async params => {
    try {
      return await client().write('save-note', params);
    } catch (e) {
      if (e.status === 401 || e.code === 'account_changed') showError(e);
      throw e;
    }
  },
  publish: state => {
    noteState.value = state;
  },
});

const note = computed({
  get: () => noteState.value.note,
  set: value => noteDraft.edit(value),
});

const noteStatusText = computed(
  () =>
    ({
      loading: 'Φόρτωση σχολίου…',
      dirty: 'Μη αποθηκευμένες αλλαγές…',
      saving: 'Αποθήκευση…',
      saved: 'Αποθηκεύτηκε στον λογαριασμό σας.',
      error: noteState.value.error,
      conflict: noteState.value.error,
    })[noteState.value.status]
);

async function saveNote() {
  try {
    await noteDraft.flush();
  } catch {
    /* visible note status; submission also requires a successful save */
  }
}

async function resolveNote(keepMine) {
  if (resolvingNote.value) return;
  resolvingNote.value = true;
  try {
    await noteDraft.resolve(keepMine);
  } catch (e) {
    error.value = e.message;
  } finally {
    resolvingNote.value = false;
  }
}

async function refreshNote() {
  if (loaded.value && !locked.value && !loginRequired.value) {
    try {
      await noteDraft.refresh();
    } catch {
      /* load/session error is visible */
    }
  }
}

const channelSelection = ref(null);
const roots = computed(() => rootOptions(items.value));
const categories = computed(() => categoryOptions(items.value, rootId.value));
const filtered = computed(() =>
  filterListItems(items.value, rootId.value, category.value, filter.value)
);
const filteredTotals = computed(() => listTotals(filtered.value));

const formatter = computed(
  () =>
    new Intl.NumberFormat('el-GR', {
      style: 'currency',
      currency: payload.value.currency || 'EUR',
      minimumFractionDigits: payload.value.price_decimals ?? 2,
      maximumFractionDigits: payload.value.price_decimals ?? 2,
    })
);
const money = value => formatter.value.format(value);

const matrix = useMatrixStore();
const matrixOpen = ref(false);
const removalNoticeOpen = ref(false);
const catalogSearch = ref(null);
const listedProductIds = computed(
  () => new Set(items.value.map(item => Number(item.id)))
);

function showError(e) {
  error.value = e.message;
  if (e.status === 401 || e.code === 'account_changed') {
    noteDraft.reset();
    payload.value = {};
    loaded.value = false;
    matrixOpen.value = false;
    removalNoticeOpen.value = false;
  }
  if (e.status === 401) loginRequired.value = true;
}

async function load() {
  loading.value = true;
  error.value = '';
  loginRequired.value = false;
  try {
    payload.value = await client().read('list-data');
    noteDraft.hydrate(payload.value.draft);
    channelSelection.value = orderChannelSelection(payload.value, channelSelection.value);
    loaded.value = true;
    removalNoticeOpen.value = !!payload.value.removedItems?.length;
  } catch (e) {
    showError(e);
  } finally {
    loading.value = false;
  }
}

async function closeRemovalNotice() {
  if (!removalNoticeOpen.value) return;
  removalNoticeOpen.value = false;
  const token = payload.value.removedNoticeToken;
  try {
    await client().write('acknowledge-removals', { notice_token: token });
    if (payload.value.removedNoticeToken === token) {
      payload.value.removedItems = [];
      payload.value.removedNoticeToken = '';
    }
  } catch (e) {
    showError(e);
  }
}

let matrixFromCatalog = false;
watch(matrixOpen, async open => {
  if (open || !matrixFromCatalog) return;
  matrixFromCatalog = false;
  await nextTick();
  catalogSearch.value?.focusInput();
});

async function openMatrix(id, fromCatalog = false) {
  if (locked.value) return;
  matrixFromCatalog = fromCatalog;
  catalogSearch.value?.hideCatalogResults();
  matrix.includeListPatch = true;
  matrix.sessionEndpoint = config.ajaxUrl;
  matrix.appearance = payload.value.appearance || {};
  matrix.apiEndpoint = config.ajaxUrl;
  matrixOpen.value = true;
  await matrix.loadProduct(id, config.ajaxUrl);
}

const confirmation = ref(null);
function closeMatrix() {
  if (matrix.loading || matrix.isSubmitting) return;
  if (matrix.isDirty) {
    confirmation.value = {
      title: 'Κλείσιμο χωρίς αποθήκευση;',
      message: 'Οι αλλαγές στις ποσότητες δεν έχουν αποθηκευτεί.',
      label: 'Απόρριψη αλλαγών',
      danger: true,
      action: () => {
        matrixOpen.value = false;
      },
    };
  } else {
    matrixOpen.value = false;
  }
}

function matrixSaved({ listPatch }) {
  try {
    payload.value = applySavedListPatch(payload.value, listPatch);
    matrixOpen.value = false;
  } catch (e) {
    showError(e);
  }
}

async function mutate(endpoint, params) {
  if (endpoint === 'empty') await noteDraft.settle();
  await client().write(endpoint, params);
  if (endpoint === 'empty') noteDraft.reset();
  if (endpoint === 'cancel-order') progress.value = null;
  await load();
}

const emptyOpen = ref(false);
const emptying = ref(false);
function confirmEmpty() {
  if (!locked.value && items.value.length) emptyOpen.value = true;
}

async function emptyList() {
  if (emptying.value || busy.value) return;
  emptying.value = true;
  busy.value = true;
  error.value = '';
  try {
    await mutate('empty', { confirm: EMPTY_CONFIRM_WORD });
    emptyOpen.value = false;
  } catch (e) {
    emptyOpen.value = false;
    showError(e);
  } finally {
    emptying.value = false;
    busy.value = false;
  }
}

function confirmRemove(item) {
  confirmation.value = {
    title: 'Αφαίρεση κωδικού;',
    message: `${item.sku} · ${item.name}`,
    label: 'Αφαίρεση',
    danger: true,
    action: () => mutate('remove-parent', { parent_id: item.id }),
  };
}

function confirmSubmit() {
  progress.value = null;
  const source = channelSelection.value?.canChoose
    ? ` Προέλευση: ${channelSelection.value.live ? 'Live δειγματισμός' : 'Online'}.`
    : '';
  confirmation.value = {
    title: 'Υποβολή της λίστας;',
    message: `${payload.value.totalQty} τεμάχια · Γενικό σύνολο ${money(payload.value.totalVal)}.${source} Μετά την υποβολή θα μεταβείτε στις παραγγελίες σας.`,
    label: 'Υποβολή',
    action: submit,
  };
}

function confirmCancel() {
  confirmation.value = {
    title: 'Ακύρωση καταχώρισης;',
    message: 'Η λίστα και οι ποσότητές σας θα διατηρηθούν για επεξεργασία.',
    label: 'Ακύρωση καταχώρισης',
    danger: true,
    action: () =>
      mutate('cancel-order', {
        order_id: activeBatch.value.wc_order_id || 0,
        token: activeBatch.value.batch_token,
      }),
  };
}

async function runConfirmation() {
  const action = confirmation.value?.action;
  if (!action || busy.value) return;
  confirmation.value = null;
  busy.value = true;
  error.value = '';
  try {
    await action();
  } catch (e) {
    showError(e);
  } finally {
    busy.value = false;
  }
}

const progressPercent = computed(() =>
  progress.value?.total
    ? Math.min(100, Math.floor((100 * progress.value.processed) / progress.value.total))
    : 0
);

const safeOrderUrl = computed(() => {
  try {
    const redirect = progress.value?.redirect;
    if (typeof redirect !== 'string' || !redirect) return '';
    const url = new URL(redirect, location.origin);
    return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol)
      ? url.href
      : '';
  } catch {
    return '';
  }
});

async function submit() {
  if (submitting.value || redirecting.value) return;
  submitting.value = true;
  busy.value = true;
  progressOpen.value = true;
  finalizing.value = false;
  submissionStage.value = 0;
  submitError.value = '';
  let destination = '';
  try {
    if (progress.value?.token) {
      progress.value = await client().write('order-status', {
        token: progress.value.token,
        order_id: progress.value.order_id,
      });
    } else {
      const revision = await noteDraft.flush();
      progress.value = await client().write('start-order', {
        note_revision: revision,
        live_showroom:
          channelSelection.value?.canChoose && channelSelection.value.live ? '1' : '0',
        quote_version: payload.value.quoteParents
          ? JSON.stringify(payload.value.quoteParents)
          : payload.value.quoteVersion,
      });
    }
    submissionStage.value = 1;
    while (!progress.value.complete && progress.value.processed < progress.value.total) {
      const before = progress.value.processed;
      progress.value = await client().write('add-batch', {
        order_id: progress.value.order_id,
        token: progress.value.token,
      });
      if (progress.value.processed <= before) {
        throw new Error('Η καταχώριση δεν προχώρησε. Δοκιμάστε Συνέχιση.');
      }
    }
    if (!progress.value.complete) {
      // Commit the 100% batch acknowledgement to the DOM even for a one-batch order.
      await nextTick();
      finalizing.value = true;
      submissionStage.value = 2;
      progress.value = await client().write('finalize-order', {
        order_id: progress.value.order_id,
        token: progress.value.token,
      });
    }
    destination = safeOrderUrl.value;
    if (!progress.value.complete || !destination) {
      throw new Error(
        'Η καταχώριση ολοκληρώθηκε, αλλά δεν βρέθηκε ασφαλής σύνδεσμος για τις παραγγελίες σας.'
      );
    }
    noteDraft.reset();
    redirecting.value = true;
    submissionStage.value = 3;
    window.location.assign(destination);
  } catch (e) {
    redirecting.value = false;
    progressOpen.value = true;
    submitError.value = e.message;
    if (e.code === 'note_conflict') noteDraft.markConflict();
    if (e.code === 'account_changed') showError(e);
    else await load();
  } finally {
    submitting.value = false;
    busy.value = false;
  }
}

function beforeLeave(event) {
  if (
    !redirecting.value &&
    (noteState.value.dirty ||
      noteState.value.status === 'saving' ||
      submitting.value ||
      (matrixOpen.value && matrix.isDirty))
  ) {
    event.preventDefault();
    event.returnValue = '';
  }
}

function restore(event) {
  if (event.persisted) {
    payload.value = {};
    channelSelection.value = null;
    loaded.value = false;
    matrixOpen.value = false;
    progress.value = null;
    progressOpen.value = false;
    redirecting.value = false;
    finalizing.value = false;
    submissionStage.value = 0;
    load();
  }
}

onMounted(() => {
  font.value = getComputedStyle(props.container).fontFamily || SYSTEM_FONT;
  load();
  window.addEventListener('focus', refreshNote);
  window.addEventListener('beforeunload', beforeLeave);
  window.addEventListener('pageshow', restore);
});

onUnmounted(() => {
  window.removeEventListener('focus', refreshNote);
  window.removeEventListener('beforeunload', beforeLeave);
  window.removeEventListener('pageshow', restore);
  noteDraft.dispose();
});
</script>

<style scoped>
.b2b-list {
  min-height: 400px;
  max-width: 80rem;
  margin-inline: auto;
  padding: 24px 12px;
  color: #0f172a;
}

@media (min-width: 768px) {
  .b2b-list {
    padding-inline: 24px;
  }
}

.list-top-card {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(340px, 0.9fr);
  align-items: center;
  gap: 20px;
  position: relative;
  z-index: 3;
  overflow: visible !important;
  padding: 14px 18px;
  margin-bottom: 18px;
  border-color: #e2e8f0 !important;
  border-top: 3px solid var(--b2b-primary) !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 14px -4px rgba(15, 23, 42, 0.05);
}

.list-hero {
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
  text-align: left;
}

.list-hero-icon {
  display: grid;
  place-items: center;
  flex: 0 0 38px;
  width: 38px;
  height: 38px;
  color: var(--b2b-primary-ink, var(--b2b-primary));
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  border-radius: var(--b2b-radius, 8px);
}

.list-hero-icon :deep(svg) {
  width: 19px;
  height: 19px;
}

.list-hero-copy {
  min-width: 0;
}

.list-hero-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.list-hero h1 {
  margin: 0 !important;
  color: #0f172a;
  font-size: clamp(18px, 1.8vw, 21px);
  line-height: 1.2;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.list-eyebrow {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: var(--b2b-radius-pill, 999px);
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.list-hero-copy > p {
  margin: 3px 0 0 !important;
  color: #64748b;
  font-size: 12px;
  line-height: 1.4;
}

.list-hero-kpis {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-variant-numeric: tabular-nums;
}

.list-hero-kpis strong {
  color: #0f172a;
  font-weight: 700;
}

.kpi-sep {
  color: #cbd5e1;
}

.kpi-val {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-weight: 700;
}

.quick-add-loading {
  height: 40px;
}

.list-content-grid {
  grid-template-columns: minmax(0, 1fr);
}

@media (min-width: 1000px) {
  .list-content-grid {
    grid-template-columns: minmax(0, 1fr) 292px;
  }
}

.product-list-card {
  border-color: #e2e8f0 !important;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04), 0 4px 16px -4px rgba(15, 23, 42, 0.05);
}

.b2b-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 40px;
  padding: 8px 16px;
  border-radius: var(--b2b-radius);
  background: var(--b2b-primary);
  color: var(--b2b-on-primary, #ffffff);
  text-decoration: none;
  font-size: 13px;
  font-weight: 600;
}

.list-loading {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 292px;
  gap: 20px;
}

.loading-main {
  display: grid;
  gap: 10px;
}

.skeleton {
  background: #eef2f7;
  animation: list-pulse 1.4s ease-in-out infinite;
  border-radius: var(--b2b-radius-lg, 12px);
}

.loading-filter {
  height: 110px;
}

.loading-product {
  display: flex;
  align-items: center;
  gap: 16px;
  height: 88px;
  padding: 14px;
  box-sizing: border-box;
}

.loading-product span {
  display: block;
  width: 50px;
  height: 60px;
  border-radius: 6px;
  background: #e1e8f1;
}

.loading-product span:nth-child(2) {
  flex: 1;
  width: auto;
  height: 42px;
}

.loading-product span:nth-child(3) {
  width: 82px;
  height: 36px;
}

.loading-aside {
  height: 520px;
}

@keyframes list-pulse {
  50% {
    opacity: 0.55;
  }
}

.removal-notice {
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: min(640px, calc(100dvh - 100px));
}

.removal-notice h2 {
  margin: 0;
  font: inherit;
  font-size: 20px;
  font-weight: 700;
}

.removal-notice p {
  margin: 0;
  color: #64748b;
  font-size: 14px;
  line-height: 1.6;
}

.removal-notice-items {
  min-height: 0;
  overflow: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.removal-notice-items > li {
  display: grid;
  gap: 4px;
  padding: 12px 0;
  border-bottom: 1px solid #e4e4e7;
  overflow-wrap: anywhere;
  font-size: 14px;
}

.removal-notice-items > li:first-child {
  padding-top: 0;
}

.removal-notice-items strong {
  color: var(--b2b-primary-ink, var(--b2b-primary));
}

.removal-notice-items small {
  color: #64748b;
  font-size: 12px;
  line-height: 1.5;
}

.removal-notice-items ul {
  margin: 2px 0 0;
  padding-left: 20px;
  font-size: 12px;
}

.removal-notice footer {
  display: flex;
  flex-shrink: 0;
  justify-content: flex-end;
}

.removal-notice footer button {
  margin: 0 !important;
}

@media (max-width: 999px) {
  .list-loading {
    grid-template-columns: 1fr;
  }
  .loading-aside {
    display: none;
  }
}

@media (max-width: 760px) {
  .list-top-card {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

@media (max-width: 620px) {
  .b2b-list {
    padding: 14px 10px;
  }
  .list-top-card {
    padding: 14px;
    margin-bottom: 14px;
  }
  .list-hero {
    gap: 10px;
  }
  .list-hero-icon {
    width: 34px;
    height: 34px;
    flex-basis: 34px;
  }
  .list-hero h1 {
    font-size: 18px;
  }
}
</style>
