export const CATALOG_SEARCH_DELAY = 250;

export function canSearchCatalog(query) {
  const text = query.trim();
  return text.length >= (/^[0-9]+$/.test(text) ? 4 : 2);
}

export function catalogWithMembership(results, productIds) {
  return results.map(product => ({ ...product, in_list: productIds.has(Number(product.id)) }));
}

// Use the visible viewport, which shrinks when an on-screen keyboard opens.
export function catalogPanelPlacement(anchor, viewport, preferSide = false) {
  const gap = 10, inset = 12;
  const left = viewport.left + inset, top = viewport.top + inset;
  const right = viewport.left + viewport.width - inset, bottom = viewport.top + viewport.height - inset;
  const availableHeight = Math.max(0, bottom - top);
  if (preferSide && anchor.left - gap - left >= 280) {
    const width = Math.min(380, anchor.left - gap - left);
    const maxHeight = Math.min(360, availableHeight);
    return { mode: 'side', left: anchor.left - gap - width, top: Math.max(top, Math.min(anchor.top - 32, bottom - maxHeight)), width, maxHeight };
  }
  const above = Math.max(0, Math.min(bottom, anchor.top - gap) - top);
  const below = Math.max(0, bottom - Math.max(top, anchor.bottom + gap));
  const upwards = above > below;
  const maxHeight = Math.min(360, upwards ? above : below);
  const width = Math.min(Math.max(anchor.width, 280), right - left);
  return { mode: upwards ? 'above' : 'below', left: Math.max(left, Math.min(anchor.left, right - width)), top: upwards ? Math.min(bottom, anchor.top - gap) - maxHeight : Math.max(top, anchor.bottom + gap), width, maxHeight };
}

// Enter must never guess from a prefix, a title, or an ambiguous SKU.
export function exactCatalogProduct(results, query) {
  const sku = query.trim().toLocaleLowerCase('en');
  if (sku.length < 2) return null;
  const matches = new Map();
  for (const product of results) {
    if (product.sku_match !== 'exact' || !Number.isInteger(product.id) || product.id <= 0) continue;
    const matchedSku = String(product.matched_sku || product.sku || '').trim().toLocaleLowerCase('en');
    if (matchedSku === sku) matches.set(product.id, product);
  }
  return matches.size === 1 ? matches.values().next().value : null;
}
