// Height covered at the top of the viewport by fixed/sticky site chrome (theme header, WP admin bar),
// measured live so a sticky table header can dock right under it. Ignores our own elements.
export function topObstruction({ root, doc = document, win = window, probes = [0.1, 0.5, 0.9], maxRatio = 0.4, minWidthRatio = 0.5 } = {}) {
  const width = win.innerWidth, limit = win.innerHeight * maxRatio;
  let bottom = 0;
  const seen = new Set();
  for (const ratio of probes) {
    for (const hit of doc.elementsFromPoint(Math.round(width * ratio), 1)) {
      if (root?.contains(hit)) continue;
      for (let el = hit; el && el !== doc.body && el !== doc.documentElement; el = el.parentElement) {
        if (seen.has(el)) break;
        seen.add(el);
        const position = win.getComputedStyle(el).position;
        if (position !== 'fixed' && position !== 'sticky') continue;
        const rect = el.getBoundingClientRect();
        // Only header-like bars: docked at the top and spanning most of the width.
        if (rect.top <= 1 && rect.height > 0 && rect.width >= width * minWidthRatio && rect.bottom <= limit) bottom = Math.max(bottom, rect.bottom);
        break;
      }
    }
  }
  return Math.max(0, Math.round(bottom));
}

// Explicit theme override, e.g. `.b2b-vue-matrix-container { --b2b-sticky-offset: 90px; }`.
export function stickyOffsetOverride(element, win = window) {
  const value = element ? win.getComputedStyle(element).getPropertyValue('--b2b-sticky-offset').trim() : '';
  const px = /^(\d+(?:\.\d+)?)px$/.exec(value);
  return px ? Number(px[1]) : null;
}
