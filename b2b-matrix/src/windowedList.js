export function windowRange(count, scrollTop, viewportHeight, rowHeight, overscan = 3) {
  if (!count || rowHeight <= 0) return { start: 0, end: 0 };
  const first = Math.min(count - 1, Math.max(0, Math.floor(Math.max(0, scrollTop) / rowHeight) - overscan));
  const last = Math.min(count, Math.ceil((Math.max(0, scrollTop) + viewportHeight) / rowHeight) + overscan);
  return { start: first, end: Math.max(first, last) };
}
