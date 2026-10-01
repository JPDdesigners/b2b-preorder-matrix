<template>
  <Teleport to="body"><dialog ref="dialog" class="b2b-dialog b2b-ui" :class="{ 'is-wide': wide, 'is-tablet-fullscreen': tabletFullscreen }" :style="themeStyle" :aria-label="label" @cancel.prevent="$emit('close')" @click="backdropClick"><div class="dialog-content"><slot /></div></dialog></Teleport>
</template>
<script setup>
import { ref, watch, nextTick, onUnmounted, inject } from 'vue';
import { THEME_KEY, appearanceStyle } from '../theme.js';
const themeStyle = inject(THEME_KEY, appearanceStyle());
const props = defineProps({ open: Boolean, wide: Boolean, tabletFullscreen: Boolean, label: { type: String, required: true } });
const emit = defineEmits(['close']);
const dialog = ref(null);
watch(() => props.open, async open => { await nextTick(); if (!dialog.value) return; if (open && !dialog.value.open) dialog.value.showModal(); else if (!open && dialog.value.open) dialog.value.close(); }, { immediate: true });
function backdropClick(event) {
  if (event.target !== dialog.value) return;
  const rect = dialog.value.getBoundingClientRect();
  if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) emit('close');
}
onUnmounted(() => dialog.value?.close());
</script>
<style scoped>
.b2b-dialog { box-sizing: border-box; width: min(520px, calc(100vw - 32px)); max-height: calc(100dvh - 48px); padding: 0; border: 1px solid #e4e4e7; border-radius: var(--b2b-radius-lg); background: white; color: #18181b; font-family: var(--b2b-font, inherit); box-shadow: 0 24px 80px #18181b30; }
.b2b-dialog::backdrop { background: #18181b80; backdrop-filter: blur(4px); }
.b2b-dialog.is-wide { width: min(1180px, calc(100vw - 24px)); }
.b2b-dialog.is-wide .dialog-content { padding: 0; }
.dialog-content { padding: 24px; }
.dialog-content :deep(button) { font: inherit; font-size: 12px; cursor: pointer; min-height: 44px; border-radius: var(--b2b-radius); }
.dialog-content :deep(button:focus-visible) { outline: 2px solid var(--b2b-primary); outline-offset: 3px; }
.dialog-content :deep(button:disabled) { opacity: .4; cursor: default; }
@media (max-width: 1024px) {
  .b2b-dialog.is-tablet-fullscreen { position: fixed; inset: 0; width: 100vw; height: 100dvh; max-width: none; max-height: none; margin: 0; border: 0; border-radius: 0; overflow: hidden; }
  .b2b-dialog.is-tablet-fullscreen .dialog-content { display: flex; flex-direction: column; width: 100%; height: 100%; min-height: 0; padding: 0; }
}
</style>
