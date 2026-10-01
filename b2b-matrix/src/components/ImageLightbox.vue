<template><DialogModal :open="Boolean(image)" :label="image?.title || 'Εικόνα προϊόντος'" @close="$emit('close')"><div v-if="image" class="image-content"><div class="image-heading"><span>{{ image.title }}</span><button type="button" aria-label="Κλείσιμο εικόνας" @click="$emit('close')"><UiIcon name="close" /></button></div><img :src="image.url" :alt="image.title" /></div></DialogModal></template>
<script setup>
import DialogModal from './DialogModal.vue';
import UiIcon from './UiIcon.vue';
defineProps({ image: { type: Object, default: null } });
defineEmits(['close']);
</script>
<style scoped>
.image-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; font-size: 14px; }
.image-heading button { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 44px; width: 44px; background: #f4f4f5; border: 0; color: #3f3f46; font-size: 24px !important; }
img { width: 100%; max-height: 65vh; object-fit: contain; display: block; border-radius: var(--b2b-radius-image); }
</style>
