// Mouse wheel over a quantity field: up adds one, down removes one.
// `scrolling` is true while a page/list scroll gesture is still in progress (see createWheelIntent):
// the wheel then keeps scrolling instead of editing the cell that slid under the pointer.
export function changeQuantityWithWheel(event, id, store, { disabled = false, desktop = false, scrolling = false } = {}) {
  if (disabled || !desktop || scrolling || !event.cancelable || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return false;
  if (!Number.isFinite(event.deltaY) || event.deltaY === 0 || Math.abs(event.deltaX || 0) > Math.abs(event.deltaY)) return false;
  event.preventDefault();
  const current = Number(store.cart[id] || 0);
  store.setQuantity(id, Math.max(0, Math.min(store.maxQty, current + (event.deltaY < 0 ? 1 : -1))));
  return true;
}

// Scroll-intent latch: a wheel gesture that starts as a scroll stays a scroll until the wheel
// has been idle for `idleMs` (trackpad inertia included), like maps/number fields in design tools.
export function createWheelIntent({ idleMs = 450, now = () => performance.now() } = {}) {
  let last = -Infinity;
  return {
    noteScroll() { last = now(); },
    active() { return now() - last < idleMs; },
    reset() { last = -Infinity; },
  };
}
