const digest = value => typeof value === 'string' && /^[a-f0-9]{64}$/.test(value);
const skuOrder = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

export function hasConfirmedListQuote(payload) {
  if (!payload.quoteParents) return digest(payload.quoteVersion);
  return (payload.items || []).every(item => digest(payload.quoteParents[item.id]));
}

export function validateListPatch(patch, productId) {
  if (!patch || !Number.isInteger(patch.parentId) || patch.parentId <= 0 || patch.parentId !== productId || !(patch.item === null ||
    (patch.item?.id === productId && Number.isInteger(patch.item.total_qty) && patch.item.total_qty > 0 &&
      Number.isFinite(patch.item.total_val) && patch.item.total_val >= 0 &&
      typeof patch.item.sku === 'string' && typeof patch.item.name === 'string' &&
      Array.isArray(patch.item.root_filters) && Array.isArray(patch.item.category_filters))) ||
    !(patch.quoteVersion === '' || digest(patch.quoteVersion))) {
    throw new Error('Η αποθήκευση δεν επέστρεψε έγκυρη ενημέρωση του κωδικού. Ανανεώστε τη λίστα.');
  }
  return patch;
}

export function applySavedListPatch(payload, patch) {
  validateListPatch(patch, patch?.parentId);
  if (!payload.quoteParents) throw new Error('Ανανεώστε τη λίστα για να εμφανιστεί η αποθηκευμένη αλλαγή.');
  const items = (payload.items || []).filter(item => item.id !== patch.parentId);
  const quoteParents = { ...payload.quoteParents };
  delete quoteParents[patch.parentId];
  if (patch.item) { items.push(patch.item); quoteParents[patch.parentId] = patch.quoteVersion; }
  items.sort((a, b) => skuOrder.compare(a.sku, b.sku));
  const factor = 10 ** (payload.price_decimals ?? 2);
  const brands = new Map();
  for (const item of items) {
    const slug = item.brand_filter_slug || item.brand_slug;
    if (!slug) continue;
    const brand = brands.get(slug) || { slug, name: item.brand_name || slug, image: item.brand_image || '', qty: 0, val: 0, parents: 0 };
    brand.qty += item.total_qty; brand.val += item.total_val; brand.parents++;
    if (!brand.image) brand.image = item.brand_image || '';
    brands.set(slug, brand);
  }
  return { ...payload, items, quoteParents, quoteVersion: '', brandSummary: [...brands.values()],
    totalQty: items.reduce((sum, item) => sum + item.total_qty, 0),
    totalVal: items.reduce((sum, item) => sum + Math.round(item.total_val * factor), 0) / factor };
}
