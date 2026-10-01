<template>
  <span
    :class="[
      'b2b-u-inline-flex b2b-u-items-center b2b-u-gap-1.5 b2b-u-font-medium b2b-u-leading-none b2b-u-box-border',
      sizeClasses,
      variantClasses
    ]"
  >
    <slot name="icon" />
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['neutral', 'primary', 'success', 'warning', 'error'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v)
  }
});

const sizeClasses = computed(() => {
  return props.size === 'sm'
    ? 'b2b-u-text-[11px] b2b-u-py-0.5 b2b-u-px-2 b2b-u-rounded-[var(--b2b-radius-sm,4px)]'
    : 'b2b-u-text-xs b2b-u-py-1 b2b-u-px-2.5 b2b-u-rounded-[var(--b2b-radius-sm,4px)]';
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'primary':
      return 'b2b-u-bg-[var(--b2b-primary-soft,#eef2ff)] b2b-u-text-[var(--b2b-primary,#4f46e5)] b2b-u-border b2b-u-border-[var(--b2b-primary-border,#c7d2fe)]';
    case 'success':
      return 'b2b-u-bg-emerald-50 b2b-u-text-emerald-700 b2b-u-border b2b-u-border-emerald-200';
    case 'warning':
      return 'b2b-u-bg-amber-50 b2b-u-text-amber-700 b2b-u-border b2b-u-border-amber-200';
    case 'error':
      return 'b2b-u-bg-rose-50 b2b-u-text-rose-700 b2b-u-border b2b-u-border-rose-200';
    case 'neutral':
    default:
      return 'b2b-u-bg-slate-100 b2b-u-text-slate-700 b2b-u-border b2b-u-border-slate-200';
  }
});
</script>
