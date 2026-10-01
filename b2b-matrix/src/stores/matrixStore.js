import { defineStore } from 'pinia';
import { normalizeAppearance } from '../theme.js';
import { openSiteLightbox } from '../siteLightbox.js';
import { validateListPatch } from '../listState.js';
const moneyFormats = new Map();
const structureCache = new Map();
const STRUCTURE_CACHE_LIMIT = 12;

export const MAX_QTY = 99999;
const messageFrom = (body) => body?.data?.message || body?.message || 'Δεν ήταν δυνατή η ολοκλήρωση της ενέργειας.';

export async function requestJSON(url, options = {}) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 20000);
  try {
    const response = await fetch(url, { ...options, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
    let body;
    try { body = await response.json(); } catch { throw new Error('Ο διακομιστής επέστρεψε μη έγκυρη απάντηση. Δοκιμάστε ξανά.'); }
    if (!response.ok || body?.success === false || body?.code) {
      const error = new Error(messageFrom(body));
      error.code = body?.code || body?.data?.code;
      throw error;
    }
    return body;
  } catch (error) {
    if (error.name === 'AbortError') throw new Error('Η σύνδεση καθυστέρησε. Η αποθήκευση δεν έχει επιβεβαιωθεί· οι αλλαγές παραμένουν εδώ.');
    throw error;
  } finally { clearTimeout(timer); }
}

export const useMatrixStore = defineStore('matrix', {
  state: () => ({
    productId: null, apiEndpoint: '', sessionEndpoint: '', nonce: '', userId: null,
    data: null, existingQuantities: {}, cart: {}, unavailableCount: 0,
    loading: false, ready: false, loginUrl: '', lightboxMode: 'site', isSubmitting: false, loadError: '', feedbackMsg: '', feedbackStatus: '',
    activeImage: null, customCurve: null, curveResetNotice: false, demo: false,
    appearance: normalizeAppearance(),
    includeListPatch: false, savedListPatch: null,
  }),
  getters: {
    cells: (state) => Object.values(state.data?.matrix || {}).flatMap(row => Object.values(row)),
    canSave: state => state.userId > 0 || Boolean(import.meta.env?.DEV && state.demo),
    maxQty: (state) => state.data?.max_qty || MAX_QTY,
    changedItems(state) { return this.cells.filter(cell => Number(state.cart[cell.id] || 0) !== Number(state.existingQuantities[cell.id] || 0)); },
    isDirty() { return this.changedItems.length > 0; },
    totalQty(state) { return this.cells.reduce((sum, cell) => sum + (Number(state.cart[cell.id]) || 0), 0); },
    previousTotalQty(state) { return this.cells.reduce((sum, cell) => sum + (Number(state.existingQuantities[cell.id]) || 0), 0); },
    currentTotalPriceRaw(state) { return this.calculatePrice(state.cart); },
    previousTotalPriceRaw(state) { return this.calculatePrice(state.existingQuantities); },
    totalPrice() { return this.cells.some(cell => cell.price == null && Number(this.cart[cell.id]) > 0) ? '—' : this.formatMoney(this.currentTotalPriceRaw); },
    previousTotalPrice() { return this.cells.some(cell => cell.price == null && Number(this.existingQuantities[cell.id]) > 0) ? '—' : this.formatMoney(this.previousTotalPriceRaw); },
    deltaQty() { return this.totalQty - this.previousTotalQty; },
    deltaPriceRaw() { return this.currentTotalPriceRaw - this.previousTotalPriceRaw; },
    deltaPrice() { if (this.totalPrice === '—' || this.previousTotalPrice === '—') return '—'; return (this.deltaPriceRaw > 0 ? '+' : '') + this.formatMoney(this.deltaPriceRaw); },
    hasExistingItems() { return this.previousTotalQty > 0; },
    activeCurveSizes(state) {
      const sizes = state.data?.sizes || [];
      if (!state.customCurve) return sizes;
      if (Array.isArray(state.customCurve)) {
        return sizes.filter(size => state.customCurve.includes(size));
      }
      return sizes.filter(size => Number(state.customCurve[size] ?? 0) > 0);
    },
    curveMultiplier: (state) => (size) => {
      if (!state.customCurve) return 1;
      if (Array.isArray(state.customCurve)) {
        return state.customCurve.includes(size) ? 1 : 0;
      }
      return Math.max(0, Number(state.customCurve[size] ?? 0));
    },
    isCustomSizesActive(state) {
      const allSizes = state.data?.sizes || [];
      return allSizes.length > 0 && this.activeCurveSizes.length < allSizes.length;
    },
    isCustomStepActive(state) {
      return this.activeCurveSizes.some(size => this.curveMultiplier(size) !== 1);
    },
    isCustomCurveActive(state) {
      return this.isCustomSizesActive || this.isCustomStepActive;
    },
    packTotalUnits(state) {
      return this.activeCurveSizes.reduce((sum, size) => sum + this.curveMultiplier(size), 0);
    },
  },
  actions: {
    formatMoney(value) {
      if (value == null) return '—';
      const currency = this.data?.currency || 'EUR';
      const decimals = this.data?.price_decimals ?? 2;
      const key = `${currency}:${decimals}`;
      if (!moneyFormats.has(key)) moneyFormats.set(key, new Intl.NumberFormat('el-GR', { style: 'currency', currency, minimumFractionDigits: decimals, maximumFractionDigits: decimals }));
      return moneyFormats.get(key).format(value);
    },
    calculatePrice(quantities) {
      const factor = 10 ** (this.data?.price_decimals ?? 2);
      return this.cells.reduce((sum, cell) => sum + Math.round(cell.price * factor) * (Number(quantities[cell.id]) || 0), 0) / factor;
    },
    colorLabel(color) { return this.data?.color_labels?.[color] || color; },
    sizeLabel(size) { return this.data?.size_labels?.[size] || size; },
    openLightbox(url, title = '', width = 0, height = 0) {
      if (!url) return;
      const image = { url, title, width, height };
      if (this.lightboxMode === 'site' && typeof window !== 'undefined' && typeof document !== 'undefined' && openSiteLightbox(image)) return;
      this.activeImage = image;
    },
    closeLightbox() { this.activeImage = null; },
    initCustomCurve() {
      try {
        const raw = localStorage.getItem('b2b_matrix_custom_curve');
        if (!raw) { this.customCurve = null; return; }
        const saved = JSON.parse(raw);
        const sizes = this.data?.sizes || [];
        if (Array.isArray(saved) && saved.length && saved.every(size => typeof size === 'string')) {
          if (sizes.length && saved.some(size => !sizes.includes(size))) { this.resetCustomCurve(); this.curveResetNotice = true; return; }
          this.customCurve = [...new Set(saved)];
          // Migrate an old full selection, preserving curves from other size systems.
          if (sizes.length && this.customCurve?.length === sizes.length && sizes.every(size => this.customCurve.includes(size))) {
            this.resetCustomCurve();
          }
        } else if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
          const keys = Object.keys(saved);
          if (sizes.length && (sizes.length !== keys.length || sizes.some(size => !keys.includes(size)))) { this.resetCustomCurve(); this.curveResetNotice = true; return; }
          if (sizes.length && sizes.length === keys.length && sizes.every(s => keys.includes(s) && Number(saved[s]) === 1)) {
            this.resetCustomCurve();
          } else {
            this.customCurve = saved;
          }
        } else {
          this.customCurve = null;
        }
      } catch { this.customCurve = null; }
    },
    setCustomCurve(val) {
      const sizes = this.data?.sizes || [];
      if (!sizes.length) return;
      if (Array.isArray(val)) {
        const chosen = sizes.filter(size => val.includes(size));
        if (!chosen.length) return;
        if (chosen.length === sizes.length) { this.resetCustomCurve(); return; }
        this.customCurve = chosen;
        try { localStorage.setItem('b2b_matrix_custom_curve', JSON.stringify(chosen)); } catch { /* Session-only. */ }
      } else if (val && typeof val === 'object') {
        const ratios = {};
        let allOnes = true;
        let total = 0;
        for (const s of sizes) {
          const num = Math.max(0, Math.min(99, Math.floor(Number(val[s] ?? 0))));
          ratios[s] = num;
          if (num !== 1) allOnes = false;
          total += num;
        }
        if (total === 0) return;
        if (allOnes) { this.resetCustomCurve(); return; }
        this.customCurve = ratios;
        try { localStorage.setItem('b2b_matrix_custom_curve', JSON.stringify(ratios)); } catch { /* Session-only. */ }
      }
    },
    resetCustomCurve() {
      this.customCurve = null;
      try { localStorage.removeItem('b2b_matrix_custom_curve'); } catch { /* Session-only. */ }
    },
    setQuantity(id, value) {
      if (this.loading || !this.ready || this.isSubmitting || !this.cells.some(cell => cell.id === Number(id))) return;
      if (value === '') { this.cart[id] = ''; return; }
      const number = Number(value);
      if (!Number.isInteger(number) || number < 0 || number > this.maxQty) {
        this.showFeedback(`Επιλέξτε ακέραιη ποσότητα από 0 έως ${this.maxQty}.`, 'error'); return;
      }
      this.cart[id] = number;
      this.feedbackMsg = '';
    },
    incrementCell(id, delta = 1) { this.setQuantity(id, Math.min(this.maxQty, Math.max(0, (Number(this.cart[id]) || 0) + delta))); },
    decrementCell(id) { this.incrementCell(id, -1); },
    incrementRow(color, delta = 1) {
      for (const size of this.activeCurveSizes) {
        const mult = this.curveMultiplier(size);
        if (mult <= 0) continue;
        const cell = this.data?.matrix?.[color]?.[size];
        if (cell) this.incrementCell(cell.id, delta * mult);
      }
    },
    resetChanges() {
      if (this.isSubmitting) return;
      this.cart = Object.fromEntries(this.cells.map(cell => [cell.id, this.existingQuantities[cell.id] || '']));
      this.feedbackMsg = '';
    },
    showFeedback(message, status = 'success') { this.feedbackMsg = message; this.feedbackStatus = status; },
    initFromDOM(container) {
      try {
        const appearance = JSON.parse(container.dataset.appearance || '{}');
        this.appearance = normalizeAppearance(appearance);
        this.lightboxMode = appearance.lightbox === 'plugin' ? 'plugin' : 'site';
      } catch { this.appearance = normalizeAppearance(); }
      this.productId = Number(container.dataset.productId);
      this.apiEndpoint = container.dataset.apiEndpoint || '/wp-json/b2b/v1/matrix';
      this.sessionEndpoint = container.dataset.sessionEndpoint || '';
      this.demo = Boolean(import.meta.env?.DEV && container.dataset.demo === 'true');
      try {
        const structure = JSON.parse(container.dataset.structure || 'null');
        if (structure?.product_id === this.productId) this.applyStructurePreview(structure);
      } catch { /* An old or malformed page cache must not prevent a fresh load. */ }
    },
    applyStructurePreview(structure) {
      const matrix = Object.fromEntries(Object.entries(structure.matrix).map(([color, row]) => [color, Object.fromEntries(Object.entries(row).map(([size, cell]) => [size, {
        id: cell.id, img: cell.img, full_img: cell.full_img, image_width: cell.image_width, image_height: cell.image_height, price: null,
      }]))]));
      const preview = { colors: structure.colors, sizes: structure.sizes, color_labels: structure.color_labels, size_labels: structure.size_labels,
        product_name: structure.product_name, parent_sku: structure.parent_sku, structure_version: structure.structure_version, matrix };
      this.validatePayload(preview);
      this.data = preview;
      this.cart = {};
      this.existingQuantities = {};
      this.ready = false;
    },
    endpointURL(endpoint) {
      const url = new URL(endpoint, window.location.origin);
      if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) throw new Error('Η διεύθυνση του API πρέπει να ανήκει στο ίδιο site.');
      return url;
    },
    isAjax() { return this.endpointURL(this.apiEndpoint).pathname.endsWith('/admin-ajax.php'); },
    async refreshSession() {
      if (!this.sessionEndpoint && !this.isAjax()) throw new Error('Λείπει η διεύθυνση σύνδεσης. Ανανεώστε το shortcode του plugin.');
      const url = this.endpointURL(this.sessionEndpoint || this.apiEndpoint);
      url.searchParams.set('action', 'b2b_matrix_session');
      const body = await requestJSON(url);
      if (body?.success !== true || !body.data?.nonce || !Number.isInteger(body.data.user_id)) throw new Error('Δεν ήταν δυνατή η επιβεβαίωση του λογαριασμού.');
      if (this.userId !== null && this.userId !== body.data.user_id) throw new Error('Ο λογαριασμός άλλαξε. Ανανεώστε τη σελίδα πριν αποθηκεύσετε.');
      this.userId = body.data.user_id;
      this.nonce = body.data.nonce;
    },
    validatePayload(payload) {
      if (!payload || !Array.isArray(payload.colors) || !Array.isArray(payload.sizes) || !payload.matrix || typeof payload.matrix !== 'object') throw new Error('Μη έγκυρα δεδομένα πίνακα.');
      const ids = new Set();
      for (const row of Object.values(payload.matrix)) {
        for (const cell of Object.values(row)) {
          if (!Number.isInteger(cell.id) || cell.id < 1 || ids.has(cell.id) || cell.price !== null && (!Number.isFinite(cell.price) || cell.price < 0)) throw new Error('Μη έγκυρη παραλλαγή ή τιμή.');
          ids.add(cell.id);
        }
      }
    },
    applyMatrixData(payload) {
      if (!payload.matrix && (!this.data || payload.structure_version !== this.data.structure_version)) throw new Error('Η δομή του πίνακα άλλαξε. Δοκιμάστε νέα φόρτωση.');
      const next = { ...(payload.matrix ? {} : this.data), ...payload };
      if (payload.prices) {
        next.matrix = Object.fromEntries(Object.entries(next.matrix).map(([color, row]) => [color, Object.fromEntries(Object.entries(row).map(([size, cell]) => {
          if (!Object.hasOwn(payload.prices, cell.id)) throw new Error('Λείπει η τρέχουσα τιμή παραλλαγής.');
          return [size, { ...cell, price: payload.prices[cell.id] }];
        }))]));
      }
      this.validatePayload(next);
      if (payload.appearance) {
        this.appearance = normalizeAppearance(payload.appearance);
        this.lightboxMode = payload.appearance.lightbox === 'plugin' ? 'plugin' : 'site';
      }
      this.data = next;
      if (next.structure_version && this.productId && next.matrix) {
        const structure = {
          product_id: this.productId, structure_version: next.structure_version,
          colors: [...next.colors], sizes: [...next.sizes], color_labels: { ...next.color_labels }, size_labels: { ...next.size_labels },
          product_name: next.product_name, parent_sku: next.parent_sku,
          matrix: Object.fromEntries(Object.entries(next.matrix).map(([color, row]) => [color, Object.fromEntries(Object.entries(row).map(([size, cell]) => [size, {
            id: cell.id, img: cell.img, full_img: cell.full_img, image_width: cell.image_width, image_height: cell.image_height,
          }]))])),
        };
        structureCache.delete(this.productId);
        structureCache.set(this.productId, structure);
        if (structureCache.size > STRUCTURE_CACHE_LIMIT) structureCache.delete(structureCache.keys().next().value);
      }
      this.nonce = payload.nonce || this.nonce;
      this.loginUrl = payload.login_url || '';
      this.applySavedQuantities(payload.existing_quantities || {});
      this.resetChanges();
      this.initCustomCurve();
      this.ready = true;
    },
    applySavedQuantities(items) {
      if (!items || typeof items !== 'object' || Array.isArray(items) && items.length) throw new Error('Η αποθήκευση δεν επέστρεψε έγκυρες ποσότητες.');
      for (const [id, qty] of Object.entries(items)) {
        if (!/^\d+$/.test(id) || !Number.isInteger(qty) || qty < 0 || qty > this.maxQty) throw new Error('Μη έγκυρες ποσότητες από τον διακομιστή.');
      }
      const visible = new Set(this.cells.map(cell => String(cell.id)));
      this.unavailableCount = Object.entries(items).filter(([id, qty]) => qty > 0 && !visible.has(id)).length;
      this.existingQuantities = Object.fromEntries(Object.entries(items).filter(([id]) => visible.has(id)));
    },
    async fetchMatrix() {
      if (this.loading || this.isSubmitting) return;
      this.loading = true;
      this.ready = false;
      this.loadError = '';
      this.curveResetNotice = false;
      this.cart = {};
      this.existingQuantities = {};
      try {
        if (import.meta.env?.DEV && this.demo) {
          const { MOCK_DATA } = await import('./mockData.js');
          this.applyMatrixData(structuredClone(MOCK_DATA)); return;
        }
        // Cookie-authenticated read bootstraps identity and data in one uncached request.
        // Legacy integrations without a session endpoint retain their REST path.
        let body;
        if (this.sessionEndpoint || this.isAjax()) {
          const url = this.endpointURL(this.sessionEndpoint || this.apiEndpoint);
          url.searchParams.set('action', 'b2b_get_matrix_data');
          url.searchParams.set('product_id', this.productId);
          if (this.data?.structure_version) url.searchParams.set('structure_version', this.data.structure_version);
          body = await requestJSON(url);
          const session = body.data;
          if (body.success !== true || !session?.nonce || !Number.isInteger(session.user_id)) throw new Error('Δεν ήταν δυνατή η επιβεβαίωση του λογαριασμού.');
          if (this.userId !== null && this.userId !== session.user_id) throw new Error('Ο λογαριασμός άλλαξε. Ανανεώστε τη σελίδα.');
          this.userId = session.user_id;
        } else {
          await this.refreshSession();
          const url = this.endpointURL(this.apiEndpoint);
          url.searchParams.set('product_id', this.productId);
          body = await requestJSON(url, { headers: { 'X-WP-Nonce': this.nonce } });
        }
        this.applyMatrixData(body.data || body);
      } catch (error) { this.loadError = error.message; }
      finally { this.loading = false; }
    },
    async loadProduct(productId, apiEndpoint = null) {
      this.productId = Number(productId);
      this.savedListPatch = null;
      if (apiEndpoint) this.apiEndpoint = apiEndpoint;
      this.data = null;
      this.ready = false;
      this.loadError = '';
      this.feedbackMsg = '';
      this.cart = {};
      this.existingQuantities = {};
      const preview = structureCache.get(this.productId);
      if (preview) this.applyStructurePreview(preview);
      await this.fetchMatrix();
    },
    async submitOrder() {
      if (this.isSubmitting || !this.isDirty || this.loading || !this.ready) return;
      if (!this.userId && !(import.meta.env?.DEV && this.demo)) {
        this.showFeedback('Συνδεθείτε στον λογαριασμό σας για να αποθηκεύσετε τη λίστα παραγγελίας.', 'error');
        return;
      }
      const items = this.changedItems.map(cell => ({ id: cell.id, qty: Number(this.cart[cell.id] || 0) }));
      if (items.some(item => !Number.isInteger(item.qty) || item.qty < 0 || item.qty > this.maxQty)) {
        this.showFeedback(`Οι ποσότητες πρέπει να είναι ακέραιες από 0 έως ${this.maxQty}.`, 'error'); return;
      }
      this.isSubmitting = true;
      this.savedListPatch = null;
      this.feedbackMsg = '';
      try {
        let saved;
        if (import.meta.env?.DEV && this.demo) {
          await new Promise(resolve => setTimeout(resolve, 350));
          saved = { ...this.existingQuantities };
          items.forEach(item => { if (item.qty) saved[item.id] = item.qty; else delete saved[item.id]; });
        } else {
          // Refresh authentication without overwriting edits or automatically retrying a write.
          await this.refreshSession();
          const ajax = this.isAjax();
          const patchOption = this.includeListPatch ? { include_list_patch: 1 } : {};
          const body = ajax ? new URLSearchParams({ action: 'b2b_save_matrix', nonce: this.nonce, product_id: this.productId, items: JSON.stringify(items), ...patchOption }) : JSON.stringify({ product_id: this.productId, items, ...patchOption });
          const response = await requestJSON(this.endpointURL(this.apiEndpoint), { method: 'POST', headers: ajax ? {} : { 'Content-Type': 'application/json', 'X-WP-Nonce': this.nonce }, body });
          if (response?.success !== true || !response.data?.items) throw new Error('Η αποθήκευση δεν επιβεβαιώθηκε. Οι αλλαγές σας παραμένουν εδώ.');
          saved = response.data.items;
          if (this.includeListPatch) this.savedListPatch = validateListPatch(response.data.listPatch, this.productId);
          this.nonce = response.data.nonce || this.nonce;
        }
        this.applySavedQuantities(saved);
        this.cart = Object.fromEntries(this.cells.map(cell => [cell.id, this.existingQuantities[cell.id] || '']));
        this.showFeedback(this.demo ? 'Η δοκιμαστική λίστα ενημερώθηκε. Δεν αποθηκεύτηκε σε κατάστημα.' : 'Οι αλλαγές αποθηκεύτηκαν στη λίστα σας.');
        return saved;
      } catch (error) { this.showFeedback(error.message || 'Πρόβλημα σύνδεσης. Οι αλλαγές σας παραμένουν εδώ.', 'error'); }
      finally { this.isSubmitting = false; }
    },
  },
});
