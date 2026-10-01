const escapeHTML = value => String(value || '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

// Reuse assets already provided by the site; never load another gallery library.
export function openSiteLightbox(image, browser = window, page = document) {
  const request = new browser.CustomEvent('b2b-matrix:open-image', { cancelable: true, detail: { ...image } });
  if (!page.dispatchEvent(request)) return true; // Optional theme adapter owns the image.
  const root = page.querySelector('.pswp');
  if (!root || typeof browser.PhotoSwipe !== 'function' || typeof browser.PhotoSwipeUI_Default !== 'function' || !(image.width > 0 && image.height > 0)) return false;
  const trigger = page.activeElement;
  let gallery;
  try {
    gallery = new browser.PhotoSwipe(root, browser.PhotoSwipeUI_Default, [{ src: image.url, w: image.width, h: image.height, title: escapeHTML(image.title) }], {
      index: 0, history: false, shareEl: false, closeOnScroll: false, showAnimationDuration: 0, hideAnimationDuration: 0,
    });
    gallery.listen('destroy', () => trigger?.isConnected && trigger.focus());
    gallery.init();
    return true;
  } catch {
    try { gallery?.destroy(); } catch { /* The plugin dialog remains available. */ }
    return false;
  }
}
