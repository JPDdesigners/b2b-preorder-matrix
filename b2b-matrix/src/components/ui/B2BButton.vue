<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'b2b-btn',
      'b2b-btn-' + variant,
      'b2b-btn-' + size,
      (disabled || loading) ? 'is-disabled' : ''
    ]"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="b2b-spinner" aria-hidden="true"></span>
    <slot name="icon">
      <UiIcon v-if="icon && !loading" :name="icon" class="b2b-btn-icon" />
    </slot>
    <slot />
  </button>
</template>

<script setup>
import UiIcon from '../UiIcon.vue';

defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'outline', 'ghost', 'danger'].includes(v)
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  },
  type: {
    type: String,
    default: 'button'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  icon: {
    type: String,
    default: ''
  }
});

defineEmits(['click']);
</script>

<style scoped>
.b2b-btn {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 8px !important;
  box-sizing: border-box !important;
  font-family: inherit !important;
  font-weight: 600 !important;
  text-decoration: none !important;
  text-transform: none !important;
  letter-spacing: normal !important;
  line-height: 1.2 !important;
  transition: all 0.15s ease !important;
  white-space: nowrap !important;
  outline-offset: 3px;
  user-select: none !important;
  margin: 0;
  cursor: pointer !important;
  vertical-align: middle !important;
}

.b2b-btn :deep(svg),
.b2b-btn-icon {
  width: 15px !important;
  height: 15px !important;
  flex-shrink: 0 !important;
}

.b2b-btn-primary {
  background: var(--b2b-primary, #4f46e5) !important;
  color: #ffffff !important;
  border: 1px solid var(--b2b-primary, #4f46e5) !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06) !important;
}
.b2b-btn-primary:hover:not(:disabled):not(.is-disabled) {
  background: var(--b2b-primary-hover, #4338ca) !important;
  border-color: var(--b2b-primary-hover, #4338ca) !important;
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15) !important;
}

.b2b-btn-outline,
.b2b-btn-secondary {
  background: #ffffff !important;
  color: #334155 !important;
  border: 1px solid #cbd5e1 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}
.b2b-btn-outline:hover:not(:disabled):not(.is-disabled),
.b2b-btn-secondary:hover:not(:disabled):not(.is-disabled) {
  background: #f8fafc !important;
  color: #0f172a !important;
  border-color: #94a3b8 !important;
}

.b2b-btn-ghost {
  background: transparent !important;
  color: #475569 !important;
  border: 1px solid transparent !important;
  box-shadow: none !important;
}
.b2b-btn-ghost:hover:not(:disabled):not(.is-disabled) {
  background: #f1f5f9 !important;
  color: #0f172a !important;
}

.b2b-btn-danger {
  background: #dc2626 !important;
  color: #ffffff !important;
  border: 1px solid #dc2626 !important;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05) !important;
}

.b2b-btn-lg {
  min-height: 48px !important;
  padding: 0 24px !important;
  font-size: 13px !important;
  border-radius: var(--b2b-radius, 8px) !important;
}

.b2b-btn-md {
  min-height: 40px !important;
  padding: 0 16px !important;
  font-size: 13px !important;
  border-radius: var(--b2b-radius, 8px) !important;
}

.b2b-btn-sm {
  min-height: 32px !important;
  padding: 0 12px !important;
  font-size: 12px !important;
  border-radius: var(--b2b-radius-sm, 4px) !important;
}

.b2b-btn:disabled,
.b2b-btn.is-disabled {
  opacity: 0.45 !important;
  cursor: default !important;
  box-shadow: none !important;
  pointer-events: none !important;
}

.b2b-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.35);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: b2b-spin 0.6s linear infinite;
  display: inline-block;
  flex-shrink: 0;
}
@keyframes b2b-spin {
  to { transform: rotate(360deg); }
}
</style>

<style scoped>.b2b-btn:focus-visible { outline: 2px solid var(--b2b-primary, #4f46e5) !important; }</style>
