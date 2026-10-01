// Mouse wheel over a quantity field: up adds one, down removes one.
export function changeQuantityWithWheel(event, id, store, { disabled = false, desktop = false } = {}) {
  if (disabled || !desktop || !event.cancelable || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return false;
  if (!Number.isFinite(event.deltaY) || event.deltaY === 0 || Math.abs(event.deltaX || 0) > Math.abs(event.deltaY)) return false;
  event.preventDefault();
  const current = Number(store.cart[id] || 0);
  store.setQuantity(id, Math.max(0, Math.min(store.maxQty, current + (event.deltaY < 0 ? 1 : -1))));
  return true;
}
