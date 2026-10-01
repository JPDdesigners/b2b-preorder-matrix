// Decide on touchend, while mobile browsers still allow keyboard activation.
// No delayed single tap, double-tap timer, or destructive long-press action.
export function createCellTouch({ onTap, onEdit, isDisabled, now = () => performance.now() }) {
  let gesture = null;
  const cancel = () => { gesture = null; };
  return {
    start(event, item) {
      cancel();
      if (isDisabled() || event.touches.length !== 1) return;
      const input = event.currentTarget.querySelector('input');
      // Once editing, leave selection/caret gestures to the native input.
      if (input && input === input.ownerDocument.activeElement) return;
      const touch = event.touches[0];
      gesture = { id: item.id, finger: touch.identifier, x: touch.clientX, y: touch.clientY, time: now(), input };
    },
    move(event) {
      if (!gesture) return;
      const touch = [...event.touches].find(t => t.identifier === gesture.finger);
      if (event.touches.length !== 1 || !touch || Math.hypot(touch.clientX - gesture.x, touch.clientY - gesture.y) > 8) cancel();
    },
    end(event, item) {
      const current = gesture;
      cancel();
      if (!current || isDisabled() || item.id !== current.id || event.touches.length) return;
      const touch = [...event.changedTouches].find(t => t.identifier === current.finger);
      if (!touch || Math.hypot(touch.clientX - current.x, touch.clientY - current.y) > 8) return;
      if (event.cancelable) event.preventDefault();
      if (now() - current.time >= 500) onEdit(current.input);
      else onTap(item);
    },
    contextMenu(event) { if (gesture) event.preventDefault(); },
    cancel,
  };
}
