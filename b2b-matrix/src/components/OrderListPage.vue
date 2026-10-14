<template>
 <main class="b2b-ui b2b-list" :style="themeStyle" :aria-busy="loading">
  <B2BCard class="list-top-card">
   <header class="list-hero">
   <div class="list-hero-icon"><UiIcon name="clipboard-check" /></div>
   <div class="list-hero-copy"><p class="list-eyebrow">ΠΡΟΠΑΡΑΓΓΕΛΙΕΣ <span v-if="payload.season">/ {{ payload.season }}</span></p><h1>Η λίστα μου</h1><p>Επιλέξτε κωδικούς και ελέγξτε τις ποσότητες πριν την υποβολή.</p></div>
   </header>
   <section v-if="loaded && !loginRequired" ref="quickAdd" class="quick-add" aria-label="Προσθήκη κωδικού" @focusout="catalogFocusOut">
    <div class="quick-add-heading"><UiIcon name="plus" /><h2>Προσθήκη κωδικού</h2></div>
    <div ref="catalogSearchRow" class="catalog-search-row"><div class="catalog-search-field"><UiIcon name="search" /><input v-model="catalogQuery" class="b2b-field" type="search" aria-label="Αναζήτηση στον κατάλογο" autocomplete="off" enterkeyhint="go" :inputmode="numeric ? 'numeric' : 'search'" :disabled="locked" placeholder="Κωδικός ή όνομα…" @input="searchCatalog()" @focus="showCatalogResults" @click="showCatalogResults" @keydown.enter="enterCatalog" @keydown.esc.prevent="hideCatalogResults" /></div><button type="button" class="keyboard-switch" :aria-pressed="numeric" :disabled="locked" :aria-label="numeric ? 'Αλλαγή σε αλφαβητικό πληκτρολόγιο' : 'Αλλαγή σε αριθμητικό πληκτρολόγιο'" @click="numeric = !numeric" @keydown.tab="tabIntoCatalog"><UiIcon name="keyboard" /><span>{{ numeric ? '123' : 'ABC' }}</span></button></div>
    <Teleport to="body">
    <div v-if="catalogResultsOpen && (searching || searchError || results.length || searched)" ref="catalogPanel" class="b2b-ui catalog-search-results" :class="'catalog-panel-' + catalogPlacement" :style="{ ...themeStyle, ...catalogPanelStyle }" :popover="catalogUsesPopover ? 'manual' : undefined" role="region" aria-label="Αποτελέσματα καταλόγου" @mousedown.prevent @focusout="catalogFocusOut" @keydown.esc.prevent="hideCatalogResults">
     <div class="catalog-results-heading"><span>Αποτελέσματα <span v-if="!searching && results.length" class="catalog-result-count">{{ results.length }}</span></span><button type="button" aria-label="Κλείσιμο αποτελεσμάτων" @click="hideCatalogResults"><UiIcon name="close" /></button></div>
     <div class="catalog-results-scroll">
     <p v-if="searching" class="catalog-search-status" role="status">Αναζήτηση…</p><p v-else-if="searchError" class="catalog-search-status catalog-search-error" role="alert">{{ searchError }}</p>
     <template v-else-if="results.length"><p v-if="catalogEnterHint" class="catalog-search-status" role="status">{{ catalogEnterHint }}</p><button v-for="product in catalogResults" :key="product.id" type="button" class="b2b-result" :disabled="locked" @click="openMatrix(product.id, true)"><img v-if="product.image" :src="product.image" alt="" loading="lazy" decoding="async" class="b2b-u-h-14 b2b-u-w-11 b2b-u-object-cover b2b-u-rounded-b2b-sm" /><span class="b2b-u-min-w-0 b2b-u-flex-1"><span class="b2b-u-block b2b-u-text-xs b2b-u-text-zinc-500">{{ product.sku }}<template v-if="product.brand_name"> · {{ product.brand_name }}</template></span><span v-if="product.matched_sku && product.matched_sku !== product.sku" class="catalog-variation-sku">SKU παραλλαγής: {{ product.matched_sku }}</span><span class="b2b-u-block b2b-u-font-medium">{{ product.name }}</span><span v-if="product.in_list" class="catalog-in-list">Ήδη στη λίστα</span></span><UiIcon :name="product.in_list ? 'edit' : 'plus'" /></button></template>
     <p v-else class="catalog-search-status" role="status">Δεν βρέθηκαν κωδικοί.</p>
     </div>
     <p v-if="!searching && results.length === 8" class="catalog-results-more">Γράψτε περισσότερα για πιο συγκεκριμένα αποτελέσματα.</p>
    </div>
    </Teleport>
   </section>
   <div v-else-if="!loginRequired" class="quick-add-loading skeleton" aria-label="Φόρτωση αναζήτησης"></div>
  </B2BCard>
  <div v-if="error" class="b2b-u-mb-5 b2b-u-rounded-b2b b2b-u-border b2b-u-border-rose-200 b2b-u-bg-rose-50 b2b-u-p-4 b2b-u-text-rose-800" role="alert">{{ error }} <B2BButton v-if="!loginRequired" variant="outline" size="sm" @click="load">Νέα προσπάθεια</B2BButton></div>
  <B2BCard v-if="loginRequired" class="b2b-u-p-8 b2b-u-text-center"><h2 class="b2b-u-text-lg b2b-u-font-semibold">Συνδεθείτε για να δείτε τη λίστα σας</h2><p class="b2b-u-my-3 b2b-u-text-zinc-500">Οι αποθηκευμένες ποσότητες ανήκουν στον λογαριασμό σας.</p><a :href="config.loginUrl" class="b2b-link">Σύνδεση στον λογαριασμό</a></B2BCard>
  <div v-else-if="loading && !loaded" class="list-loading" role="status" aria-label="Φόρτωση λίστας"><div class="loading-main"><div class="loading-filter skeleton"></div><div v-for="i in 5" :key="i" class="loading-product skeleton"><span></span><span></span><span></span></div></div><div class="loading-aside skeleton"></div></div>
  <template v-else-if="loaded">
   <div v-if="activeBatch" class="b2b-u-mb-6 b2b-u-flex b2b-u-flex-wrap b2b-u-items-center b2b-u-justify-between b2b-u-gap-3 b2b-u-rounded-b2b b2b-u-border b2b-u-border-amber-200 b2b-u-bg-amber-50 b2b-u-p-4"><div><strong>Υπάρχει καταχώριση σε εξέλιξη</strong><p class="b2b-u-text-xs b2b-u-mt-1">Συνεχίστε ή ακυρώστε την καταχώριση για να επεξεργαστείτε τη λίστα.</p></div><div class="b2b-u-flex b2b-u-flex-wrap b2b-u-gap-2"><B2BButton variant="outline" :disabled="busy" @click="confirmCancel">Ακύρωση καταχώρισης</B2BButton><B2BButton :disabled="busy" @click="submit">Συνέχιση</B2BButton></div></div>
   <div class="b2b-u-grid b2b-u-items-start b2b-u-gap-6 list-content-grid">
    <div class="b2b-u-min-w-0">
     <B2BCard class="product-list-card">
      <div class="products-heading"><div class="section-heading"><span class="section-icon"><UiIcon name="layers" /></span><h2>Κωδικοί σχεδίου <span class="count-pill">{{ items.length }}</span></h2></div></div>
      <div v-if="items.length" class="list-filters">
       <div class="list-filter-actions"><div class="root-tabs" role="group" aria-label="Κατηγορίες ρίζας"><button type="button" :class="{ active: !rootId }" :aria-pressed="!rootId" @click="rootId = ''; category = ''">Όλα</button><button v-for="root in roots" :key="root.id" type="button" :class="{ active: rootId === String(root.id) }" :aria-pressed="rootId === String(root.id)" @click="rootId = rootId === String(root.id) ? '' : String(root.id); category = ''">{{ root.name }}</button></div><B2BButton class="empty-list-button" variant="outline" size="sm" icon="trash" :disabled="locked" @click="confirmEmpty">Άδειασμα λίστας</B2BButton></div>
       <div v-if="items.length" class="filter-toolbar"><div class="filter-controls"><div class="category-select"><select v-model="category" class="b2b-field" aria-label="Φίλτρο υποκατηγορίας"><option value="">Όλες οι υποκατηγορίες</option><option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ !rootId && c.root_name ? c.root_name + ' / ' + c.name : c.name }}</option></select><UiIcon name="chevron-down" /></div><div class="list-search-field"><UiIcon name="search" /><input v-model="filter" class="b2b-field" type="search" aria-label="Αναζήτηση στη λίστα" placeholder="Αναζήτηση στη λίστα…" /></div></div><div class="filter-subtotal" role="status"><div class="subtotal-heading"><UiIcon name="clipboard-check" /><span>Υποσύνολο προβολής</span></div><div class="subtotal-body"><div class="subtotal-counts"><span>{{ filtered.length }} {{ filtered.length === 1 ? 'κωδικός' : 'κωδικοί' }}</span><span>{{ filteredTotals.qty }} τεμάχια</span></div><strong v-if="loading" class="inline-skeleton wide"></strong><strong v-else>{{ filteredTotals.unpriced ? '—' : money(filteredTotals.value) }}</strong></div></div></div>
      </div>
      <div v-if="!items.length" class="b2b-u-py-16 b2b-u-px-6 b2b-u-text-center"><div class="b2b-u-mx-auto b2b-u-mb-4 b2b-u-flex b2b-u-h-12 b2b-u-w-12 b2b-u-items-center b2b-u-justify-center b2b-u-rounded-b2b-lg b2b-u-bg-primary-soft b2b-u-text-primary"><UiIcon name="clipboard-check" /></div><h3 class="b2b-u-text-lg b2b-u-font-semibold">Η λίστα σας είναι έτοιμη να γεμίσει</h3><p class="b2b-u-mt-2 b2b-u-text-sm b2b-u-text-zinc-500">Βρείτε έναν κωδικό και επιλέξτε ποσότητες στον πίνακα.</p></div>
      <div v-else-if="!filtered.length" class="b2b-u-p-10 b2b-u-text-center b2b-u-text-zinc-500">Δεν υπάρχουν κωδικοί με αυτά τα φίλτρα.<B2BButton variant="ghost" @click="filter = ''; rootId = ''; category = ''">Καθαρισμός φίλτρων</B2BButton></div>
      <div v-if="filtered.length" class="product-columns-heading" :style="{ paddingRight: (25 + listScrollbarWidth) + 'px' }" aria-hidden="true"><span>Κωδικός</span><span>Τεμάχια</span><span>Αξία</span><span></span></div>
      <div v-if="filtered.length" ref="listViewport" class="product-window" :style="{ height: listHeight + 'px' }" role="list" aria-label="Κωδικοί λίστας" tabindex="0" @scroll="onListScroll"><div class="b2b-list-virtual-spacer" :style="{ height: filtered.length * rowHeight + 'px' }">
       <div v-for="(item, index) in visibleItems" :key="item.id" class="b2b-list-virtual-row" :style="{ top: (visibleRange.start + index) * rowHeight + 'px', height: rowHeight + 'px' }"><article class="b2b-list-product-row" role="listitem" :aria-posinset="visibleRange.start + index + 1" :aria-setsize="filtered.length">
        <div class="b2b-list-product-main"><div class="b2b-list-product-media"><button type="button" class="b2b-list-product-remove" :disabled="locked" :aria-label="'Αφαίρεση ' + item.name" title="Αφαίρεση κωδικού" @click="confirmRemove(item)"><UiIcon name="trash" /></button><img v-if="item.image" :src="item.image" :alt="item.name" loading="lazy" /><span v-else class="b2b-list-product-image-empty"><UiIcon name="layers" /></span></div>
        <div class="b2b-list-product-info"><p class="b2b-list-product-meta"><span :title="item.sku">{{ item.sku || 'Χωρίς κωδικό' }}</span><span v-if="item.brand_name" class="b2b-list-brand-badge" :title="item.brand_name">{{ item.brand_name }}</span></p><h3 :title="item.name">{{ item.name }}</h3><p v-if="item.unavailable || item.unpriced" class="b2b-list-product-warning" :title="item.unavailable ? 'Περιέχει μη διαθέσιμες παραλλαγές' : 'Δεν υπάρχει τιμή για όλες τις παραλλαγές'"><UiIcon name="alert" /><span>{{ item.unavailable ? 'Περιέχει μη διαθέσιμες παραλλαγές' : 'Δεν υπάρχει τιμή για όλες τις παραλλαγές' }}</span></p></div></div>
        <div class="b2b-list-product-column b2b-list-product-quantity"><span class="b2b-list-product-column-label">Τεμάχια</span><span v-if="loading" class="inline-skeleton" aria-label="Φόρτωση ποσότητας"></span><strong v-else>{{ item.total_qty }}</strong></div>
        <div class="b2b-list-product-column b2b-list-product-value"><span class="b2b-list-product-column-label">Αξία</span><span v-if="loading" class="inline-skeleton wide" aria-label="Φόρτωση αξίας"></span><strong v-else>{{ item.unpriced ? '—' : money(item.total_val) }}</strong></div>
        <B2BButton class="b2b-list-product-edit" variant="outline" size="md" icon="edit" :disabled="locked || item.unavailable" @click="openMatrix(item.id)">Αλλαγή</B2BButton>
       </article></div>
      </div></div><div v-if="items.length" class="list-bottom-bar"><p>{{ filtered.length }} από {{ items.length }} κωδικοί</p></div>
     </B2BCard>
    </div>
    <aside class="list-sidebar" aria-label="Στοιχεία και σύνοψη προπαραγγελίας">
     <B2BCard class="sidebar-card">
      <section class="sidebar-section customer-section" aria-label="Στοιχεία πελάτη"><div class="section-heading"><span class="section-icon"><UiIcon name="user" /></span><h2>Στοιχεία πελάτη</h2></div><dl class="customer-details"><div><dt>Επωνυμία</dt><dd><span v-if="loading" class="inline-skeleton wide"></span><strong v-else>{{ payload.customerInfo?.company || '—' }}</strong></dd></div><div><dt>ΑΦΜ</dt><dd><span v-if="loading" class="inline-skeleton"></span><strong v-else>{{ payload.customerInfo?.afm || '—' }}</strong></dd></div><div><dt>Email τιμολόγησης</dt><dd><span v-if="loading" class="inline-skeleton wide"></span><strong v-else class="customer-email">{{ payload.customerInfo?.email || '—' }}</strong></dd></div></dl></section>
      <section class="sidebar-section note-section" aria-label="Σημείωση πελάτη"><div class="section-heading"><span class="section-icon"><UiIcon name="edit" /></span><h2><label :for="noteId">Σημείωση πελάτη</label></h2></div><textarea :id="noteId" v-model="note" class="b2b-field" rows="3" maxlength="4000" :disabled="locked || noteState.status === 'loading'" :aria-describedby="noteId + '-status'" placeholder="Κάτι που θέλετε να γνωρίζουμε…" @blur="saveNote"></textarea><p :id="noteId + '-status'" class="note-save-status" :class="{ 'note-save-error': ['error', 'conflict'].includes(noteState.status) }" role="status" aria-live="polite">{{ noteStatusText }}</p><p v-if="noteState.status === 'conflict' && noteState.remoteNote !== null" class="note-remote-preview"><strong>Αποθηκευμένο σχόλιο:</strong><br />{{ noteState.remoteNote || '(Κενό σχόλιο)' }}</p><div v-if="noteState.status === 'conflict'" class="note-conflict-actions"><B2BButton size="sm" variant="outline" :disabled="locked" @click="resolveNote(false)">Φόρτωση αποθηκευμένου</B2BButton><B2BButton size="sm" variant="outline" :disabled="locked" @click="resolveNote(true)">Κράτημα δικού μου</B2BButton></div><B2BButton v-else-if="noteState.status === 'error'" size="sm" variant="outline" :disabled="locked" @click="saveNote">Δοκιμή ξανά</B2BButton></section>
      <section v-if="channelSelection?.canChoose" class="sidebar-section order-channel-section" aria-label="Προέλευση παραγγελίας">
       <label class="order-channel-toggle"><input v-model="channelSelection.live" type="checkbox" :disabled="locked" /><span>Παραγγελία από live δειγματισμό</span></label>
       <p v-if="activeBatch">Η προέλευση έχει αποθηκευτεί στην καταχώριση{{ activeBatch.order_channel === 'unknown' ? ' ως μη καταγεγραμμένη' : '' }}.</p>
       <p v-else>Επιλέξτε όταν καταχωρίζετε την παραγγελία μαζί με τον πελάτη στον δειγματισμό.</p>
      </section>
      <section class="sidebar-section summary-section" aria-label="Σύνοψη λίστας"><div class="section-heading"><span class="section-icon"><UiIcon name="clipboard-check" /></span><h2>Σύνοψη λίστας</h2></div><div class="summary-total"><div class="summary-value"><span>Γενικό σύνολο</span><strong v-if="loading" class="inline-skeleton wide"></strong><strong v-else>{{ hasUnpriced ? '—' : money(payload.totalVal || 0) }}</strong><dl class="summary-rows"><div><dt>Κωδικοί</dt><dd><strong v-if="loading" class="inline-skeleton"></strong><strong v-else>{{ items.length }}</strong></dd></div><div><dt>Τεμάχια</dt><dd><strong v-if="loading" class="inline-skeleton"></strong><strong v-else>{{ payload.totalQty || 0 }}</strong></dd></div></dl></div><B2BButton class="submit-list" icon="send" size="lg" :disabled="locked || !items.length || hasInvalid" @click="confirmSubmit">Υποβολή λίστας</B2BButton></div></section>
     </B2BCard>
    </aside>
   </div>
  </template>
  <MatrixModal v-if="matrixOpen" :store="matrix" @close="closeMatrix" @saved="matrixSaved" />
  <DialogModal :open="removalNoticeOpen" label="Ακυρωμένα είδη" @close="closeRemovalNotice">
   <section class="removal-notice">
    <h2>Ακυρωμένα είδη</h2>
    <p>Οι παρακάτω οδηγοί ή παραλλαγές έχουν ακυρωθεί και αφαιρέθηκαν από τη λίστα σας.</p>
    <ul class="removal-notice-items">
     <li v-for="item in payload.removedItems" :key="item.id"><strong v-if="item.sku">{{ item.sku }}</strong><span>{{ item.name }}</span><template v-if="item.partial"><small>Αφαιρέθηκαν μόνο οι ακυρωμένες παραλλαγές. Οι υπόλοιπες επιλογές σας παραμένουν στη λίστα.</small><ul><li v-for="variation in item.variations" :key="variation.id">{{ variation.sku || variation.name }}</li></ul></template></li>
    </ul>
    <footer><B2BButton size="lg" autofocus @click="closeRemovalNotice">Ενημερώθηκα</B2BButton></footer>
   </section>
  </DialogModal>
  <DialogModal :open="!!confirmation" :label="confirmation?.title || 'Επιβεβαίωση'" @close="!busy && (confirmation = null)"><h2 class="b2b-u-text-lg b2b-u-font-semibold">{{ confirmation?.title }}</h2><p class="b2b-u-my-4 b2b-u-text-sm b2b-u-text-zinc-500">{{ confirmation?.message }}</p><div class="b2b-u-flex b2b-u-justify-end b2b-u-gap-2"><B2BButton variant="outline" :disabled="busy" @click="confirmation = null">Πίσω</B2BButton><B2BButton :variant="confirmation?.danger ? 'danger' : 'primary'" :loading="busy" @click="runConfirmation">{{ confirmation?.label || 'Επιβεβαίωση' }}</B2BButton></div></DialogModal>
  <EmptyListDialog :open="emptyOpen" :busy="emptying" :item-count="items.length" :total-qty="Number(payload.totalQty) || 0" :total-value="hasUnpriced ? '—' : money(payload.totalVal || 0)" :has-note="!!String(note || '').trim()" @close="emptyOpen = false" @confirm="emptyList" />
  <DialogModal :open="progressOpen" label="Καταχώριση προπαραγγελίας" @close="!submitting && !redirecting && (progressOpen = false)">
   <div class="submission-progress">
    <span class="submission-pill"><UiIcon :name="submitError ? 'alert' : 'clock'" />{{ submitError ? 'Η καταχώριση σταμάτησε' : redirecting ? 'Μετάβαση στις παραγγελίες' : finalizing ? 'Ολοκλήρωση παραγγελίας' : 'Σε εξέλιξη' }}</span>
    <h2>Καταχώριση προπαραγγελίας</h2>
    <p class="submission-instruction">Κρατήστε αυτό το παράθυρο ανοιχτό μέχρι να ολοκληρωθεί η καταχώριση.</p>
    <p class="submission-help">Αν διακοπεί η σύνδεση, μπορείτε να συνεχίσετε από το ίδιο σημείο.</p>
    <ol class="submission-stages" aria-label="Στάδια καταχώρισης"><li v-for="(stage, index) in submissionStages" :key="stage" :class="{ done: submissionStage > index, current: submissionStage === index }" :aria-current="submissionStage === index ? 'step' : undefined"><span>{{ submissionStage > index ? '✓' : index + 1 }}</span>{{ stage }}</li></ol>
    <div class="submission-meter"><div class="submission-counter" role="status" aria-live="polite"><span v-if="redirecting">Η παραγγελία καταχωρίστηκε. Μεταφέρεστε στις παραγγελίες σας…</span><span v-else-if="finalizing">Υπολογισμός συνόλων και ολοκλήρωση παραγγελίας…</span><span v-else-if="submissionStage === 1 && progress?.total">{{ progress.processed }} / {{ progress.total }} γραμμές παραγγελίας</span><span v-else>Αποθήκευση σχολίου και προετοιμασία λίστας…</span><strong v-if="submissionStage === 1 || progress?.complete">{{ progressPercent }}%</strong></div><div class="b2b-progress" :class="{ 'is-indeterminate': !submitError && !progress?.complete && submissionStage !== 1, 'is-stopped': !!submitError }" role="progressbar" aria-label="Πρόοδος τρέχοντος σταδίου" :aria-valuemin="submissionStage === 1 || progress?.complete ? 0 : undefined" :aria-valuemax="submissionStage === 1 || progress?.complete ? 100 : undefined" :aria-valuenow="submissionStage === 1 || progress?.complete ? progressPercent : undefined" :aria-valuetext="submissionStages[submissionStage]"><span :style="{ width: (progress?.complete ? 100 : submissionStage === 1 ? progressPercent : 35) + '%' }"></span></div><p v-if="finalizing && progress?.total" class="submission-lines-done">Καταχώριση γραμμών: {{ progress.processed }} / {{ progress.total }} · {{ progressPercent }}%</p></div>
    <template v-if="submitError"><p role="alert" class="submission-error">{{ submitError }}</p><div class="submission-actions"><B2BButton variant="outline" @click="progressOpen = false">Κλείσιμο</B2BButton><B2BButton icon="refresh" @click="submit">Συνέχιση</B2BButton></div></template>
   </div>
  </DialogModal>
 </main>
</template>

<script setup>
import { ref, computed, watch, provide, nextTick, onMounted, onUnmounted, useId } from 'vue';
import { orderChannelSelection } from '../orderChannel.js';
import { createDraftNote } from '../draftNote.js';
import { applySavedListPatch, hasConfirmedListQuote } from '../listState.js';
import { exactCatalogProduct, canSearchCatalog, catalogWithMembership, catalogPanelPlacement, CATALOG_SEARCH_DELAY } from '../catalogSearch.js';
import B2BButton from './ui/B2BButton.vue';
import B2BCard from './ui/B2BCard.vue';
import UiIcon from './UiIcon.vue';
import DialogModal from './DialogModal.vue';
import MatrixModal from './MatrixModal.vue';
import EmptyListDialog from './EmptyListDialog.vue';
import { EMPTY_CONFIRM_WORD } from '../emptyConfirm.js';
import { appearanceStyle, THEME_KEY, SYSTEM_FONT } from '../theme.js';
import { useMatrixStore } from '../stores/matrixStore.js';
import { createListApi } from '../listApi.js';
import { windowRange } from '../windowedList.js';
import { rootOptions, categoryOptions, filterListItems, listTotals } from '../listView.js';
const props = defineProps({ container: { type: Object, required: true } });
let config = {};
try { config = JSON.parse(props.container.dataset.b2bConfig || '{}'); } catch { /* load displays a connection error */ }
const noteId = useId(), payload = ref({}), loaded = ref(false), loading = ref(false), busy = ref(false), error = ref(''), loginRequired = ref(false);
const font = ref(SYSTEM_FONT), themeStyle = computed(() => appearanceStyle(payload.value.appearance, font.value));
provide(THEME_KEY, themeStyle);
const items = computed(() => payload.value.items || []), activeBatch = computed(() => payload.value.activeBatch);
const resolvingNote = ref(false);
const locked = computed(() => busy.value || loading.value || resolvingNote.value || redirecting.value || !!activeBatch.value);
const hasUnpriced = computed(() => items.value.some(i => i.unpriced)), hasInvalid = computed(() => !hasConfirmedListQuote(payload.value) || items.value.some(i => i.unavailable || i.unpriced));
const rootId = ref(''), category = ref(''), filter = ref('');
const noteState = ref({ note: '', dirty: false, status: 'loading', error: '' });
const noteDraft = createDraftNote({
 read: async () => { try { return (await client().read('draft-note')).draft; } catch (e) { showError(e); throw e; } },
 write: async params => { try { return await client().write('save-note', params); } catch (e) { if (e.status === 401 || e.code === 'account_changed') showError(e); throw e; } },
 publish: state => { noteState.value = state; },
});
const note = computed({ get: () => noteState.value.note, set: value => noteDraft.edit(value) });
const noteStatusText = computed(() => ({ loading: 'Φόρτωση σχολίου…', dirty: 'Μη αποθηκευμένες αλλαγές…', saving: 'Αποθήκευση…', saved: 'Αποθηκεύτηκε στον λογαριασμό σας.', error: noteState.value.error, conflict: noteState.value.error })[noteState.value.status]);
async function saveNote() { try { await noteDraft.flush(); } catch { /* visible note status; submission also requires a successful save */ } }
async function resolveNote(keepMine) { if (resolvingNote.value) return; resolvingNote.value = true; try { await noteDraft.resolve(keepMine); } catch (e) { error.value = e.message; } finally { resolvingNote.value = false; } }
async function refreshNote() { if (loaded.value && !locked.value && !loginRequired.value) { try { await noteDraft.refresh(); } catch { /* load/session error is visible */ } } }
const channelSelection = ref(null);
const roots = computed(() => rootOptions(items.value));
const categories = computed(() => categoryOptions(items.value, rootId.value));
const filtered = computed(() => filterListItems(items.value, rootId.value, category.value, filter.value));
const filteredTotals = computed(() => listTotals(filtered.value));
const listViewport = ref(null), listScrollTop = ref(0), rowHeight = ref(112);
const listScrollbarWidth = ref(0);
let listResizeObserver;
watch(listViewport, element => {
 listResizeObserver?.disconnect(); listScrollbarWidth.value = 0;
 if (!element) return;
 const alignColumns = () => { listScrollbarWidth.value = Math.max(0, element.offsetWidth - element.clientWidth); };
 listResizeObserver = new ResizeObserver(alignColumns); listResizeObserver.observe(element); alignColumns();
});
const listHeight = computed(() => Math.min(560, filtered.value.length * rowHeight.value));
const visibleRange = computed(() => windowRange(filtered.value.length, listScrollTop.value, listHeight.value, rowHeight.value));
const visibleItems = computed(() => filtered.value.slice(visibleRange.value.start, visibleRange.value.end));
function syncRowHeight() { rowHeight.value = window.matchMedia('(max-width: 620px)').matches ? 176 : 112; }
function onListScroll(event) { listScrollTop.value = event.target.scrollTop; }
watch([rootId, category, filter, () => items.value.length], () => { listScrollTop.value = 0; if (listViewport.value) listViewport.value.scrollTop = 0; });
const formatter = computed(() => new Intl.NumberFormat('el-GR', { style: 'currency', currency: payload.value.currency || 'EUR', minimumFractionDigits: payload.value.price_decimals ?? 2, maximumFractionDigits: payload.value.price_decimals ?? 2 }));
const money = value => formatter.value.format(value);
let api;
function client() { return api ||= createListApi(config.ajaxUrl); }
function showError(e) { error.value = e.message; if (e.status === 401 || e.code === 'account_changed') { noteDraft.reset(); payload.value = {}; loaded.value = false; matrixOpen.value = false; removalNoticeOpen.value = false; } if (e.status === 401) loginRequired.value = true; }
async function load() {
 loading.value = true; error.value = ''; loginRequired.value = false;
 try { payload.value = await client().read('list-data'); noteDraft.hydrate(payload.value.draft); channelSelection.value = orderChannelSelection(payload.value, channelSelection.value); loaded.value = true; removalNoticeOpen.value = !!payload.value.removedItems?.length; }
 catch (e) { showError(e); } finally { loading.value = false; }
}
const removalNoticeOpen = ref(false);
async function closeRemovalNotice() {
 if (!removalNoticeOpen.value) return;
 removalNoticeOpen.value = false;
 const token = payload.value.removedNoticeToken;
 try {
  await client().write('acknowledge-removals', { notice_token: token });
  if (payload.value.removedNoticeToken === token) { payload.value.removedItems = []; payload.value.removedNoticeToken = ''; }
 } catch (e) { showError(e); }
}
const keyboardKey = 'b2b_list_numeric_keyboard';
function savedKeyboardMode() { try { return localStorage.getItem(keyboardKey) === '1'; } catch { return false; } }
const catalogQuery = ref(''), results = ref([]), searching = ref(false), searched = ref(false), searchError = ref(''), numeric = ref(savedKeyboardMode());
const quickAdd = ref(null), catalogResultsOpen = ref(false);
const listedProductIds = computed(() => new Set(items.value.map(item => Number(item.id))));
const catalogResults = computed(() => catalogWithMembership(results.value, listedProductIds.value));
const catalogPanel = ref(null);
const catalogUsesPopover = typeof HTMLElement !== 'undefined' && 'showPopover' in HTMLElement.prototype;
watch(catalogPanel, element => {
 if (!element) return;
 positionCatalogResults();
 if (catalogUsesPopover) {
  try { element.showPopover({ source: quickAdd.value?.querySelector('input') }); }
  catch { element.removeAttribute('popover'); }
 }
}, { flush: 'post' });
const catalogSearchRow = ref(null), catalogPanelStyle = ref({}), catalogPlacement = ref('below');
function positionCatalogResults() {
 if (!catalogResultsOpen.value || !catalogSearchRow.value) return;
 const view = window.visualViewport;
 const viewport = { left: view?.offsetLeft || 0, top: view?.offsetTop || 0, width: view?.width || window.innerWidth, height: view?.height || window.innerHeight };
 const keyboardOpen = viewport.height < window.innerHeight - 120;
 const preferSide = window.innerWidth <= 1024 || window.matchMedia('(pointer: coarse)').matches || keyboardOpen;
 const placement = catalogPanelPlacement(catalogSearchRow.value.getBoundingClientRect(), viewport, preferSide);
 catalogPlacement.value = placement.mode;
 catalogPanelStyle.value = { left: placement.left + 'px', top: placement.top + 'px', width: placement.width + 'px', maxHeight: placement.maxHeight + 'px' };
}
function showCatalogResults() { catalogResultsOpen.value = canSearchCatalog(catalogQuery.value); positionCatalogResults(); }
watch(catalogResultsOpen, async open => { if (open) { await nextTick(); positionCatalogResults(); } });
const catalogEnterHint = ref('');
function hideCatalogResults() { catalogResultsOpen.value = false; pendingCatalogEnter = false; }
function insideCatalog(target) { return !!target && (quickAdd.value?.contains(target) || catalogPanel.value?.contains(target)); }
function catalogFocusOut(event) { if (!insideCatalog(event.relatedTarget)) hideCatalogResults(); }
function dismissCatalogResults(event) { if (!insideCatalog(event.target)) hideCatalogResults(); }
function tabIntoCatalog(event) {
 if (event.shiftKey || catalogPanel.value?.hasAttribute('popover')) return;
 const button = catalogPanel.value?.querySelector('button');
 if (button) { event.preventDefault(); button.focus(); }
}
watch(numeric, value => { try { localStorage.setItem(keyboardKey, value ? '1' : '0'); } catch { /* Browser storage may be unavailable. */ } });
let searchTimer, searchController, searchVersion = 0, searchedCatalogQuery = '', pendingCatalogEnter = false;
function openExactCatalog(query) {
 const product = exactCatalogProduct(results.value, query);
 if (product) openMatrix(product.id, true);
 else catalogEnterHint.value = 'Επιλέξτε κωδικό από τα αποτελέσματα.';
}
function enterCatalog(event) {
 if (event.isComposing || event.repeat || locked.value || matrixOpen.value) return;
 event.preventDefault();
 const query = catalogQuery.value.trim();
 if (!canSearchCatalog(query) || pendingCatalogEnter) return;
 catalogResultsOpen.value = true;
 if (searched.value && !searching.value && searchedCatalogQuery === query) openExactCatalog(query);
 else searchCatalog(true, true);
}
function searchCatalog(immediate = false, openOnComplete = false) {
 clearTimeout(searchTimer); searchController?.abort(); const version = ++searchVersion;
 const query = catalogQuery.value.trim(); pendingCatalogEnter = openOnComplete;
 catalogEnterHint.value = '';
 results.value = []; searched.value = false; searchError.value = ''; searching.value = false;
 catalogResultsOpen.value = canSearchCatalog(query);
 if (!catalogResultsOpen.value) return;
 positionCatalogResults();
 searching.value = true;
 const runSearch = async () => {
  searchController = new AbortController();
  try {
   const data = await client().read('search-products', { q: query }, searchController.signal);
   if (version !== searchVersion) return;
   results.value = data; searched.value = true; searchedCatalogQuery = query;
   const shouldOpen = pendingCatalogEnter; pendingCatalogEnter = false;
   if (shouldOpen && query === catalogQuery.value.trim() && !locked.value && !matrixOpen.value) openExactCatalog(query);
  }
  catch (e) { if (version === searchVersion) { pendingCatalogEnter = false; if (e.name !== 'AbortError') searchError.value = e.message; } }
  finally { if (version === searchVersion) searching.value = false; }
 };
 if (immediate) runSearch(); else searchTimer = setTimeout(runSearch, CATALOG_SEARCH_DELAY);
}
const matrix = useMatrixStore(), matrixOpen = ref(false);
let matrixFromCatalog = false;
watch(matrixOpen, async open => {
 if (open || !matrixFromCatalog) return;
 matrixFromCatalog = false;
 await nextTick();
 const input = quickAdd.value?.querySelector('input');
 if (!input || matrixOpen.value) return;
 input.focus({ preventScroll: true });
 input.setSelectionRange(input.value.length, input.value.length);
 hideCatalogResults();
});
async function openMatrix(id, fromCatalog = false) {
 if (locked.value) return;
 matrixFromCatalog = fromCatalog;
 hideCatalogResults();
 matrix.includeListPatch = true;
 matrix.sessionEndpoint = config.ajaxUrl; matrix.appearance = payload.value.appearance || {};
 matrix.apiEndpoint = config.ajaxUrl; matrixOpen.value = true;
 await matrix.loadProduct(id, config.ajaxUrl);
}
const confirmation = ref(null);
function closeMatrix() {
 if (matrix.loading || matrix.isSubmitting) return;
 if (matrix.isDirty) confirmation.value = { title: 'Κλείσιμο χωρίς αποθήκευση;', message: 'Οι αλλαγές στις ποσότητες δεν έχουν αποθηκευτεί.', label: 'Απόρριψη αλλαγών', danger: true, action: () => { matrixOpen.value = false; } };
 else matrixOpen.value = false;
}
function matrixSaved({ listPatch }) {
 try { payload.value = applySavedListPatch(payload.value, listPatch); matrixOpen.value = false; }
 catch (e) { showError(e); }
}
async function mutate(endpoint, params) { if (endpoint === 'empty') await noteDraft.settle(); await client().write(endpoint, params); if (endpoint === 'empty') noteDraft.reset(); if (endpoint === 'cancel-order') progress.value = null; await load(); }
const emptyOpen = ref(false), emptying = ref(false);
function confirmEmpty() { if (!locked.value && items.value.length) emptyOpen.value = true; }
async function emptyList() {
 if (emptying.value || busy.value) return;
 emptying.value = true; busy.value = true; error.value = '';
 try { await mutate('empty', { confirm: EMPTY_CONFIRM_WORD }); emptyOpen.value = false; }
 catch (e) { emptyOpen.value = false; showError(e); }
 finally { emptying.value = false; busy.value = false; }
}
function confirmRemove(item) { confirmation.value = { title: 'Αφαίρεση κωδικού;', message: `${item.sku} · ${item.name}`, label: 'Αφαίρεση', danger: true, action: () => mutate('remove-parent', { parent_id: item.id }) }; }
function confirmSubmit() { progress.value = null; const source = channelSelection.value?.canChoose ? ` Προέλευση: ${channelSelection.value.live ? 'Live δειγματισμός' : 'Online'}.` : ''; confirmation.value = { title: 'Υποβολή της λίστας;', message: `${payload.value.totalQty} τεμάχια · Γενικό σύνολο ${money(payload.value.totalVal)}.${source} Μετά την υποβολή θα μεταβείτε στις παραγγελίες σας.`, label: 'Υποβολή', action: submit }; }
function confirmCancel() { confirmation.value = { title: 'Ακύρωση καταχώρισης;', message: 'Η λίστα και οι ποσότητές σας θα διατηρηθούν για επεξεργασία.', label: 'Ακύρωση καταχώρισης', danger: true, action: () => mutate('cancel-order', { order_id: activeBatch.value.wc_order_id || 0, token: activeBatch.value.batch_token }) }; }
async function runConfirmation() { const action = confirmation.value?.action; if (!action || busy.value) return; confirmation.value = null; busy.value = true; error.value = ''; try { await action(); } catch (e) { showError(e); } finally { busy.value = false; } }
const progress = ref(null), progressOpen = ref(false), submitting = ref(false), redirecting = ref(false), submitError = ref('');
const finalizing = ref(false);
const submissionStages = ['Προετοιμασία', 'Καταχώριση γραμμών', 'Σύνολα και ολοκλήρωση', 'Μετάβαση'];
const submissionStage = ref(0);
const progressPercent = computed(() => progress.value?.total ? Math.min(100, Math.floor(100 * progress.value.processed / progress.value.total)) : 0);
const safeOrderUrl = computed(() => { try { const redirect = progress.value?.redirect; if (typeof redirect !== 'string' || !redirect) return ''; const url = new URL(redirect, location.origin); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } });
async function submit() {
 if (submitting.value || redirecting.value) return;
 submitting.value = true; busy.value = true; progressOpen.value = true; finalizing.value = false; submissionStage.value = 0; submitError.value = '';
 let destination = '';
 try {
  if (progress.value?.token) progress.value = await client().write('order-status', { token: progress.value.token, order_id: progress.value.order_id });
  else { const revision = await noteDraft.flush(); progress.value = await client().write('start-order', { note_revision: revision, live_showroom: channelSelection.value?.canChoose && channelSelection.value.live ? '1' : '0', quote_version: payload.value.quoteParents ? JSON.stringify(payload.value.quoteParents) : payload.value.quoteVersion }); }
  submissionStage.value = 1;
  while (!progress.value.complete && progress.value.processed < progress.value.total) {
   const before = progress.value.processed;
   progress.value = await client().write('add-batch', { order_id: progress.value.order_id, token: progress.value.token });
   if (progress.value.processed <= before) throw new Error('Η καταχώριση δεν προχώρησε. Δοκιμάστε Συνέχιση.');
  }
  if (!progress.value.complete) {
   // Commit the 100% batch acknowledgement to the DOM even for a one-batch order.
   await nextTick();
   finalizing.value = true; submissionStage.value = 2;
   progress.value = await client().write('finalize-order', { order_id: progress.value.order_id, token: progress.value.token });
  }
  destination = safeOrderUrl.value;
  if (!progress.value.complete || !destination) throw new Error('Η καταχώριση ολοκληρώθηκε, αλλά δεν βρέθηκε ασφαλής σύνδεσμος για τις παραγγελίες σας.');
  noteDraft.reset();
  redirecting.value = true; submissionStage.value = 3;
  window.location.assign(destination);
 } catch (e) { redirecting.value = false; progressOpen.value = true; submitError.value = e.message; if (e.code === 'note_conflict') noteDraft.markConflict(); if (e.code === 'account_changed') showError(e); else await load(); }
 finally { submitting.value = false; busy.value = false; }
}
function beforeLeave(event) { if (!redirecting.value && (noteState.value.dirty || noteState.value.status === 'saving' || submitting.value || (matrixOpen.value && matrix.isDirty))) { event.preventDefault(); event.returnValue = ''; } }
function restore(event) { if (event.persisted) { payload.value = {}; channelSelection.value = null; loaded.value = false; matrixOpen.value = false; progress.value = null; progressOpen.value = false; redirecting.value = false; finalizing.value = false; submissionStage.value = 0; load(); } }
onMounted(() => window.addEventListener('focus', refreshNote));
onUnmounted(() => { window.removeEventListener('focus', refreshNote); noteDraft.dispose(); });
onMounted(() => { font.value = getComputedStyle(props.container).fontFamily || SYSTEM_FONT; syncRowHeight(); window.addEventListener('resize', syncRowHeight); window.addEventListener('resize', positionCatalogResults); window.addEventListener('scroll', positionCatalogResults, true); window.visualViewport?.addEventListener('resize', positionCatalogResults); window.visualViewport?.addEventListener('scroll', positionCatalogResults); document.addEventListener('pointerdown', dismissCatalogResults); load(); window.addEventListener('beforeunload', beforeLeave); window.addEventListener('pageshow', restore); });
onUnmounted(() => { listResizeObserver?.disconnect(); clearTimeout(searchTimer); searchController?.abort(); ++searchVersion; pendingCatalogEnter = false; document.removeEventListener('pointerdown', dismissCatalogResults); window.removeEventListener('resize', syncRowHeight); window.removeEventListener('resize', positionCatalogResults); window.removeEventListener('scroll', positionCatalogResults, true); window.visualViewport?.removeEventListener('resize', positionCatalogResults); window.visualViewport?.removeEventListener('scroll', positionCatalogResults); window.removeEventListener('beforeunload', beforeLeave); window.removeEventListener('pageshow', restore); });
</script>
<style scoped>
.order-channel-toggle { display: flex; align-items: center; gap: 12px; min-height: 44px; cursor: pointer; color: #334155; font-size: 13px; font-weight: 600; line-height: 1.5; }
.order-channel-toggle input { flex: 0 0 21px; width: 21px; height: 21px; margin: 0; accent-color: var(--b2b-primary); }
.order-channel-toggle:has(input:disabled) { cursor: default; opacity: .7; }
.order-channel-section p { margin: 8px 0 0 !important; color: #64748b; font-size: 12px; line-height: 1.6; }
@media (max-width: 999px) { .order-channel-section { grid-column: 1 / -1; } }
.b2b-list { min-height: 400px; }
.b2b-list { max-width: 80rem; margin-inline: auto; padding: 32px 12px; }
@media (min-width: 768px) { .b2b-list { padding-inline: 24px; } }
.b2b-result { display: flex; width: 100%; align-items: center; gap: 12px; padding: 12px 4px; margin: 0; border: 0; border-radius: 0; color: #27272a; background: transparent; text-align: left; font-size: 13px; line-height: 1.5; cursor: pointer; box-shadow: none; }
.b2b-result:hover { background: #fafafa; }
.b2b-link { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 8px 16px; border-radius: var(--b2b-radius); background: var(--b2b-primary); color: white; text-decoration: none; font-size: 13px; font-weight: 600; }
.b2b-progress { display: block; width: 100%; height: 8px; border: 0; border-radius: 8px; overflow: hidden; background: #e4e4e7; }
.b2b-progress > span { display: block; height: 100%; background: var(--b2b-primary); transition: width .2s ease; }
.b2b-progress.is-indeterminate > span { animation: b2b-submission-busy 1.4s ease-in-out infinite; }
.b2b-progress.is-stopped > span { opacity: .45; }
@keyframes b2b-submission-busy { from { transform: translateX(-100%); } to { transform: translateX(290%); } }
.submission-stages { list-style: none !important; display: grid; gap: 10px; padding: 0 !important; margin: 20px 0 !important; }
.submission-stages li { display: flex; align-items: center; gap: 10px; color: #64748b; font-size: 13px; }
.submission-stages li > span { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: #f4f4f5; }
.submission-stages li.current { color: var(--b2b-primary); font-weight: 700; }
.submission-stages li.current > span, .submission-stages li.done > span { background: var(--b2b-primary); color: #fff; }
.submission-lines-done, .note-save-status { color: #64748b; font-size: 12px; line-height: 1.5; margin: 8px 0 0 !important; }
.note-save-error { color: #b91c1c; }
.note-conflict-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.note-remote-preview { white-space: pre-wrap; overflow-wrap: anywhere; max-height: 120px; overflow: auto; padding: 10px; margin: 8px 0 !important; background: #fafafa; border: 1px solid #e4e4e7; font-size: 12px; line-height: 1.5; }
@media (prefers-reduced-motion: reduce) { .b2b-progress > span { transition: none; } .b2b-progress.is-indeterminate > span { animation: none; width: 100% !important; opacity: .65; } }
.submission-progress h2 { margin: 16px 0 10px !important; color: #172033; font-size: 22px; line-height: 1.3; font-weight: 700; letter-spacing: -.025em; }
.submission-pill { display: inline-flex; align-items: center; gap: 7px; padding: 6px 11px; border-radius: var(--b2b-radius-pill, 999px); background: var(--b2b-primary-soft); border: 1px solid var(--b2b-primary-border); color: var(--b2b-primary); font-size: 11px; font-weight: 700; line-height: 1.4; }
.submission-pill :deep(svg) { width: 15px; height: 15px; flex-basis: 15px; }
.submission-instruction { margin: 0 !important; color: #334155; font-size: 14px; font-weight: 600; line-height: 1.65; }
.submission-help { margin: 8px 0 0 !important; color: #64748b; font-size: 12px; line-height: 1.65; }
.submission-meter { margin-top: 24px; padding: 16px; border: 1px solid #e9edf3; border-radius: var(--b2b-radius, 8px); background: #f8fafc; }
.submission-counter { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 12px; color: #64748b; font-size: 12px; font-variant-numeric: tabular-nums; }
.submission-counter strong { color: var(--b2b-primary); font-size: 15px; }
.submission-error { margin: 16px 0 0 !important; color: #be123c; font-size: 13px; line-height: 1.6; }
.submission-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 8px; margin-top: 20px; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.b2b-list { color: #1e293b; }
.list-top-card { display: grid; grid-template-columns: minmax(0,1fr) minmax(360px, .95fr); align-items: center; gap: 28px; position: relative; z-index: 3; overflow: visible !important; padding: 20px; margin-bottom: 20px; border-color: var(--b2b-primary-border) !important; border-top: 6px solid var(--b2b-primary-soft) !important; }
.list-hero { display: flex; align-items: center; gap: 15px; min-width: 0; text-align: left; }
.list-hero-icon, .section-icon { display: grid; place-items: center; flex: 0 0 auto; color: var(--b2b-primary); background: var(--b2b-primary-soft); border: 1px solid var(--b2b-primary-border); border-radius: var(--b2b-radius, 9px); }
.list-hero-icon { width: 42px; height: 42px; }
.list-hero-icon :deep(svg) { width: 21px; height: 21px; }
.list-hero-copy { min-width: 0; }
.list-eyebrow { margin: 0 0 2px !important; color: var(--b2b-primary); font-size: 10px; font-weight: 700; letter-spacing: .045em; }
.list-hero h1 { margin: 0 !important; color: #172033; font-size: clamp(19px, 2vw, 23px); line-height: 1.2; font-weight: 700; letter-spacing: -.025em; }
.list-hero-copy > p:last-child { margin: 3px 0 0 !important; color: #64748b; font-size: 12px; line-height: 1.4; }
.list-content-grid { grid-template-columns: minmax(0, 1fr); }
.quick-add { position: relative; min-width: 0; padding-left: 24px; border-left: 1px solid var(--b2b-primary-border); }
.quick-add-heading { display: flex; align-items: center; gap: 7px; color: var(--b2b-primary); }
.quick-add-heading h2 { margin: 0 !important; color: #334155; font-size: 12px; font-weight: 700; }
.quick-add-heading :deep(svg) { width: 16px; height: 16px; flex-basis: 16px; }
.quick-add-loading { height: 68px; }
.catalog-search-results { position: fixed; inset: auto; margin: 0; z-index: 2147483647; display: flex; flex-direction: column; padding: 6px; box-sizing: border-box; border: 1px solid var(--b2b-primary-border); border-radius: var(--b2b-radius, 8px); background: #fff; box-shadow: 0 12px 32px #17203326; }
.catalog-results-scroll { min-height: 0; overflow-y: auto; overscroll-behavior: contain; }
.catalog-results-heading { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex: 0 0 auto; padding: 0 4px 4px 8px; border-bottom: 1px solid var(--b2b-primary-border); color: #334155; font-size: 12px; font-weight: 600; }
.catalog-results-heading > button { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; padding: 0; margin: 0; border: 0; border-radius: var(--b2b-radius-sm, 4px); color: #64748b; background: transparent; cursor: pointer; }
.catalog-results-heading > button:hover { color: var(--b2b-primary); background: var(--b2b-primary-soft); }
.catalog-result-count { margin-left: 4px; color: var(--b2b-primary); }
.catalog-results-more { flex: 0 0 auto; margin: 0 !important; padding: 8px; border-top: 1px solid var(--b2b-primary-border); color: #64748b; font-size: 11px; line-height: 1.4; }
.b2b-result { padding: 10px 8px; border-radius: var(--b2b-radius-sm, 4px); }
.b2b-result:hover, .b2b-result:focus-visible { color: var(--b2b-primary); background: var(--b2b-primary-soft); }
.catalog-search-status { margin: 0 !important; padding: 12px; color: #64748b; font-size: 12px; }
.catalog-search-error { color: #be123c; }
.catalog-in-list { display: inline-flex; margin-top: 5px; padding: 3px 7px; border: 1px solid var(--b2b-primary-border); border-radius: var(--b2b-radius-sm, 4px); background: var(--b2b-primary-soft); color: var(--b2b-primary); font-size: 11px; line-height: 1.4; font-weight: 600; }
.catalog-variation-sku { display: block; margin-top: 2px; color: #64748b; font-size: 11px; }
.product-list-card { --list-product-columns: minmax(0,1fr) 70px 116px 116px; --list-product-gap: 12px; border-color: var(--b2b-primary-border) !important; }
.list-filters { padding: 16px 20px; border-bottom: 1px solid #dce4ee; }
.section-heading { display: flex; align-items: center; gap: 11px; min-width: 0; }
.section-icon { width: 36px; height: 36px; }
.section-icon :deep(svg) { width: 17px; height: 17px; }
.section-heading h2, .section-heading h2 label { margin: 0 !important; color: #172033; font-size: 15px; line-height: 1.3; font-weight: 700; }
.section-heading p { margin: 2px 0 0 !important; color: #64748b; font-size: 11px; }
.catalog-search-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; margin-top: 9px; }
.catalog-search-field { position: relative; min-width: 0; }
.catalog-search-field > :deep(svg) { position: absolute; top: 50%; left: 13px; transform: translateY(-50%); width: 18px; height: 18px; color: #94a3b8; pointer-events: none; }
.catalog-search-field input { padding-left: 40px !important; }
.keyboard-switch { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-width: 86px; height: 44px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: var(--b2b-radius, 8px); background: #fff; color: #334155; font: inherit; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
.keyboard-switch[aria-pressed="true"] { border-color: var(--b2b-primary-border); background: var(--b2b-primary-soft); color: var(--b2b-primary); }
.keyboard-switch :deep(svg) { width: 17px; height: 17px; }
.keyboard-switch:focus-visible, .b2b-list-product-remove:focus-visible { outline: 2px solid var(--b2b-primary); outline-offset: 2px; }
.keyboard-switch:disabled, .b2b-list-product-remove:disabled { opacity: .5; cursor: default; }
.products-heading { padding: 12px 20px; background: var(--b2b-primary-soft); border-bottom: 1px solid var(--b2b-primary-border); }
.products-heading .section-icon { width: 30px; height: 30px; background: #fff; }
.products-heading h2 { color: var(--b2b-primary); }
.count-pill { display: inline-flex; align-items: center; justify-content: center; min-width: 22px; height: 22px; margin-left: 5px; padding: 0 5px; border-radius: 999px; background: #eef2f7; color: #475569; font-size: 11px; }
.list-filter-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.root-tabs { display: flex; align-items: center; gap: 5px; width: max-content; min-width: 0; max-width: 100%; overflow-x: auto; scrollbar-width: thin; padding: 3px; border: 1px solid #cdd8e6; border-radius: var(--b2b-radius, 8px); background: #f8fafc; }
.root-tabs button { flex: 0 0 auto; min-height: 34px; padding: 5px 12px; border: 1px solid transparent; border-radius: var(--b2b-radius-sm, 6px); background: transparent; color: #475569; font: inherit; font-size: 12px; font-weight: 650; white-space: nowrap; cursor: pointer; }
.root-tabs button:hover:not(.active) { color: var(--b2b-primary); background: #fff; }
.root-tabs button.active { border-color: var(--b2b-primary-border); background: #fff; color: var(--b2b-primary); box-shadow: 0 1px 4px rgba(15,23,42,.08); }
.root-tabs button:focus-visible { outline: 2px solid var(--b2b-primary); outline-offset: 1px; }
.b2b-list .empty-list-button { flex-shrink: 0; color: #475569 !important; border-color: #cbd5e1 !important; background: #fff !important; }
.b2b-list .empty-list-button:is(:hover, :focus-visible):not(:disabled):not(.is-disabled) { color: #dc2626 !important; border-color: #dc2626 !important; background: #fff1f2 !important; }
.filter-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, .85fr); align-items: stretch; gap: 12px; }
.filter-controls { display: grid; grid-template-columns: minmax(0, 1fr); grid-template-rows: repeat(2, 44px); gap: 10px; min-width: 0; }
.list-search-field, .category-select { position: relative; min-width: 0; }
.list-search-field > :deep(svg) { position: absolute; top: 50%; left: 13px; transform: translateY(-50%); width: 17px; height: 17px; color: #94a3b8; pointer-events: none; }
.list-search-field input { padding-left: 39px !important; }
.category-select select { width: 100%; padding-right: 36px !important; appearance: none !important; cursor: pointer; }
.category-select > :deep(svg) { position: absolute; top: 50%; right: 12px; transform: translateY(-50%); width: 16px; height: 16px; color: var(--b2b-primary); pointer-events: none; }
.filter-subtotal { min-width: 0; border: 1px solid var(--b2b-primary-border); border-radius: var(--b2b-radius, 8px); background: var(--b2b-primary-soft); overflow: hidden; }
.subtotal-heading { display: flex; align-items: center; gap: 7px; padding: 8px 13px; border-bottom: 1px solid var(--b2b-primary-border); color: var(--b2b-primary); font-size: 11px; font-weight: 700; }
.subtotal-heading :deep(svg) { width: 14px; height: 14px; flex-basis: 14px; }
.subtotal-body { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 10px 13px; }
.subtotal-counts { display: grid; gap: 3px; color: #64748b; font-size: 11px; }
.subtotal-body > strong { color: var(--b2b-primary); font-size: clamp(18px, 1.6vw, 22px); line-height: 1; font-variant-numeric: tabular-nums; white-space: nowrap; }
.product-columns-heading { display: grid; grid-template-columns: var(--list-product-columns); column-gap: var(--list-product-gap); align-items: center; padding: 8px 25px; background: #f8fafc; border-bottom: 1px solid #dce4ee; color: #64748b; font-size: 10px; font-weight: 700; text-align: center; }
.product-window { overflow-y: auto; overscroll-behavior-y: auto; scrollbar-width: thin; }
.product-window:focus-visible { outline: 2px solid var(--b2b-primary); outline-offset: -2px; }
.b2b-list-virtual-spacer { position: relative; }
.b2b-list-virtual-row { position: absolute; inset-inline: 0; box-sizing: border-box; }
.b2b-list-product-row { display: grid; grid-template-columns: var(--list-product-columns); column-gap: var(--list-product-gap); align-items: center; height: calc(100% - 12px); margin: 6px 12px; padding: 10px 12px; border: 1px solid #d5deea; border-radius: var(--b2b-radius, 8px); background: #fff; box-sizing: border-box; overflow: hidden; }
.b2b-list-product-row:hover { border-color: var(--b2b-primary-border); background: #fafbfd; }
.b2b-list-product-main { display: flex; align-items: center; gap: 12px; min-width: 0; min-height: 0; margin: 0; padding: 0 12px 0 0; }
.b2b-list-product-column { display: flex; flex-direction: column; justify-content: center; gap: 4px; min-width: 0; min-height: 58px; padding: 0 12px; border-left: 1px solid #e5eaf1; font-variant-numeric: tabular-nums; }
.b2b-list-product-column strong { color: #172033; font-size: 13px; font-weight: 700; white-space: nowrap; }
.b2b-list-product-column-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
.b2b-list-product-quantity { align-items: center; }
.b2b-list-product-value { align-items: center; }
.b2b-list-product-media { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; margin: 0; padding: 0; }
.b2b-list-product-media img, .b2b-list-product-image-empty { display: block; width: 56px; height: 66px; flex: 0 0 56px; margin: 0; padding: 0; object-fit: cover; border: 1px solid #dce4ee; border-radius: var(--b2b-radius, 8px); background: #f8fafc; }
.b2b-list-product-image-empty { display: grid; place-items: center; color: #94a3b8; }
.b2b-list-product-remove { display: grid; place-items: center; width: 32px; height: 32px; padding: 0; border: 1px solid #cbd5e1; border-radius: var(--b2b-radius, 8px); background: #fff; color: #475569; cursor: pointer; transition: color .15s ease, background-color .15s ease, border-color .15s ease; }
.b2b-list-product-remove:is(:hover, :focus-visible):not(:disabled) { color: #dc2626; background: #fff1f2; border-color: #dc2626; }
.b2b-list-product-remove :deep(svg) { width: 16px; height: 16px; }
.b2b-list-product-info { min-width: 0; min-height: 0; margin: 0; padding: 0; }
.b2b-list-product-meta { display: flex; align-items: center; gap: 6px; margin: 0 0 5px !important; color: #64748b; font-size: 11px; }
.b2b-list-product-meta > span:first-child { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.b2b-list-brand-badge { flex: 0 0 auto; max-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 2px 6px; border: 1px solid #e2e8f0; border-radius: var(--b2b-radius-sm, 4px); background: #f8fafc; color: #475569; }
.b2b-list-product-info h3 { display: -webkit-box; overflow: hidden; -webkit-box-orient: vertical; -webkit-line-clamp: 2; margin: 0 !important; color: #172033; font-size: 14px; line-height: 1.35; font-weight: 700; }
.b2b-list-product-warning { display: flex; align-items: center; gap: 5px; margin: 5px 0 0 !important; color: #a16207; font-size: 11px; }
.b2b-list-product-warning :deep(svg) { width: 14px; height: 14px; }
.b2b-list-product-warning span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.b2b-list .b2b-list-product-edit { align-self: center; justify-self: end; color: #334155 !important; border-color: #cbd5e1 !important; background: #fff !important; }
.b2b-list .b2b-list-product-edit:is(:hover, :focus-visible):not(:disabled):not(.is-disabled) { color: var(--b2b-primary) !important; border-color: var(--b2b-primary) !important; background: var(--b2b-primary-soft) !important; }
.list-bottom-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; padding: 12px 20px; border-top: 1px solid #e9edf3; }
.list-bottom-bar p { margin: 0 !important; color: #64748b; font-size: 11px; }
.list-loading { display: grid; grid-template-columns: minmax(0, 1fr) 288px; gap: 24px; }
.loading-main { display: grid; gap: 12px; }
.skeleton { background: #eef2f7; animation: list-pulse 1.4s ease-in-out infinite; border-radius: var(--b2b-radius-lg, 12px); }
.inline-skeleton { display: inline-block; width: 42px; height: 13px; vertical-align: middle; border-radius: 4px; background: #e2e8f0; animation: list-pulse 1.4s ease-in-out infinite; }
.inline-skeleton.wide { width: 94px; }
.loading-filter { height: 226px; }
.loading-product { display: flex; align-items: center; gap: 16px; height: 100px; padding: 16px; box-sizing: border-box; }
.loading-product span { display: block; width: 56px; height: 66px; border-radius: 8px; background: #e1e8f1; }
.loading-product span:nth-child(2) { flex: 1; width: auto; height: 48px; }
.loading-product span:nth-child(3) { width: 82px; height: 38px; }
.loading-aside { height: 650px; }
@keyframes list-pulse { 50% { opacity: .55; } }
.list-sidebar { min-width: 0; }
.sidebar-card { display: grid; border-color: var(--b2b-primary-border) !important; }
.sidebar-section { min-width: 0; padding: 18px; }
.sidebar-section + .sidebar-section { border-top: 1px solid var(--b2b-primary-border); }
.sidebar-section .section-heading { margin: -18px -18px 16px; padding: 12px 18px; background: var(--b2b-primary-soft); border-bottom: 1px solid var(--b2b-primary-border); }
.sidebar-section .section-icon { width: 26px; height: 26px; border: 0; background: transparent; }
.sidebar-section .section-heading h2, .sidebar-section .section-heading label { font-size: 13px; }
.customer-details { display: grid; gap: 12px; margin: 0 !important; }
.customer-details > div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.customer-details dt { color: #64748b; font-size: 10px; font-weight: 500; }
.customer-details dd, .summary-rows dd { margin: 0 !important; }
.customer-details strong { color: #172033; font-size: 12px; font-weight: 650; overflow-wrap: anywhere; }
.customer-email { font-weight: 500 !important; }
.summary-rows { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; margin: 12px 0 0 !important; padding-top: 12px; border-top: 1px solid #ffffff25; }
.summary-rows > div { display: flex; justify-content: center; align-items: baseline; flex-wrap: wrap; gap: 5px; font-size: 11px; }
.summary-rows > div + div { border-left: 1px solid #ffffff25; }
.summary-rows dt { color: #cbd5e1; font-weight: 500; }
.summary-rows strong { color: #fff; font-weight: 700; }
.summary-total { min-width: 0; }
.summary-value { display: flex; flex-direction: column; align-items: center; min-width: 0; padding: 18px 12px; border-radius: var(--b2b-radius, 8px); border-bottom: 4px solid var(--b2b-primary); background: #172033; text-align: center; }
.summary-value > span { color: #cbd5e1; font-size: 11px; }
.summary-value > strong { margin-top: 6px; color: var(--b2b-primary-soft); font-size: clamp(22px, 2.2vw, 28px); line-height: 1.2; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
.submit-list { width: 100%; margin-top: 14px !important; }
.note-section textarea { min-height: 88px; resize: vertical; margin-bottom: 0 !important; }
.b2b-result { border-radius: var(--b2b-radius, 8px); padding: 10px; }
.b2b-result:hover { background: #f4f7fb; }
@media (min-width: 1000px) { .list-content-grid { grid-template-columns: minmax(0, 1fr) 288px; } .list-sidebar { position: sticky; top: 24px; } }
@media (max-width: 999px) { .sidebar-card { grid-template-columns: minmax(0,1fr) minmax(0,1fr); } .note-section { border-top: 0 !important; border-left: 1px solid var(--b2b-primary-border); } .summary-section { grid-column: 1 / -1; } .summary-total { display: flex; align-items: center; justify-content: space-between; gap: 18px; } .summary-value { flex: 1; } .submit-list { width: auto; min-width: 210px; margin-top: 0 !important; } .list-loading { grid-template-columns: 1fr; } .loading-aside { display: none; } }
@media (max-width: 760px) { .list-top-card { grid-template-columns: 1fr; gap: 16px; } .quick-add { padding: 16px 0 0; border-left: 0; border-top: 1px solid var(--b2b-primary-border); } .catalog-search-results { left: 0; } }
@media (max-width: 620px) { .b2b-list { padding: 16px 10px; } .list-top-card { padding: 16px; margin-bottom: 16px; } .list-hero { gap: 11px; } .list-hero-icon { width: 38px; height: 38px; } .list-hero h1 { font-size: 19px; } .products-heading, .list-filters { padding: 12px 16px; } .sidebar-section { padding: 16px; } .sidebar-section .section-heading { margin: -16px -16px 14px; padding: 12px 16px; } .filter-toolbar, .sidebar-card { grid-template-columns: 1fr; } .note-section { border-left: 0; border-top: 1px solid var(--b2b-primary-border) !important; } .summary-total { display: block; } .submit-list { width: 100%; margin-top: 14px !important; } .root-tabs { width: 100%; } .product-columns-heading { display: none; } .b2b-list-product-row { grid-template-columns: minmax(0,1fr) minmax(0,1.4fr) auto; gap: 6px 0; margin: 6px 10px; padding: 10px; } .b2b-list-product-main { grid-column: 1 / -1; padding-right: 0; gap: 10px; } .b2b-list-product-media { gap: 6px; } .b2b-list-product-column { min-height: 40px; padding: 0 8px; } .b2b-list-product-quantity { align-items: flex-start; border-left: 0; } .b2b-list-product-value { align-items: flex-start; } .b2b-list-product-column-label { position: static; width: auto; height: auto; clip-path: none; color: #64748b; font-size: 10px; } .b2b-list-product-edit { grid-column: 3; } .submission-progress h2 { font-size: 20px; } }
.removal-notice { display: flex; flex-direction: column; gap: 14px; max-height: min(640px, calc(100dvh - 100px)); }
.removal-notice h2 { margin: 0; font: inherit; font-size: 20px; font-weight: 700; }
.removal-notice p { margin: 0; color: #64748b; font-size: 14px; line-height: 1.6; }
.removal-notice-items { min-height: 0; overflow: auto; margin: 0; padding: 0; list-style: none; }
.removal-notice-items > li { display: grid; gap: 4px; padding: 12px 0; border-bottom: 1px solid #e4e4e7; overflow-wrap: anywhere; font-size: 14px; }
.removal-notice-items > li:first-child { padding-top: 0; }
.removal-notice-items strong { color: var(--b2b-primary); }
.removal-notice-items small { color: #64748b; font-size: 12px; line-height: 1.5; }
.removal-notice-items ul { margin: 2px 0 0; padding-left: 20px; font-size: 12px; }
.removal-notice footer { display: flex; flex-shrink: 0; justify-content: flex-end; }
.removal-notice footer button { margin: 0 !important; }
</style>
