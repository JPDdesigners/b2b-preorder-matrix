<template>
  <DialogModal :open="modelValue" label="Ρύθμιση καμπύλης μεγεθών" @close="close">
    <div class="curve-header">
      <div><span class="eyebrow">ΚΑΜΠΥΛΗ ΠΡΟΪΟΝΤΟΣ</span><h3>Ρύθμιση καμπύλης</h3></div>
      <button type="button" class="close-button" aria-label="Κλείσιμο" @click="close"><UiIcon name="close" /></button>
    </div>
    <p class="description">Ορίστε πόσα τεμάχια από κάθε μέγεθος θα προστεθούν ή θα αφαιρεθούν με μία καμπύλη στο επιλεγμένο χρώμα. Το 0 εξαιρεί το μέγεθος. Σε οθόνη αφής, ένα πάτημα προσθέτει 1 και κράτημα 0,5 δευτ. ανοίγει το αριθμητικό πληκτρολόγιο.</p>
    <div class="ratio-grid">
      <label v-for="size in availableSizes" :key="size" class="ratio-field" @touchstart.passive="touch.start($event, { id: size })" @touchmove.passive="touch.move($event)" @touchend="touch.end($event, { id: size })" @touchcancel="touch.cancel" @contextmenu="touch.contextMenu">
        <span>{{ sizeLabels?.[size] || size }}</span>
        <input type="text" inputmode="numeric" pattern="[0-9]*" :value="localRatios[size]" :aria-label="'Τεμάχια ανά σειρά για ' + (sizeLabels?.[size] || size)" @focus="$event.target.select()" @input="setMultiplier(size, $event)" />
      </label>
    </div>
    <p class="curve-preview" :class="{ empty: totalUnits === 0 }" role="status">
      <template v-if="totalUnits">Μία σειρά: <strong>{{ totalUnits }} τεμ.</strong> <span>{{ ratioChain }}</span></template>
      <template v-else>Ορίστε τουλάχιστον ένα τεμάχιο σε ένα μέγεθος.</template>
    </p>
    <div class="curve-footer">
      <B2BButton variant="outline" size="md" @click="selectAll">Όλα ×1</B2BButton>
      <div class="footer-actions"><B2BButton variant="ghost" size="md" @click="close">Ακύρωση</B2BButton><B2BButton variant="primary" size="md" :disabled="totalUnits === 0" @click="save">Εφαρμογή</B2BButton></div>
    </div>
  </DialogModal>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import DialogModal from './DialogModal.vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';
import { createCellTouch } from '../cellTouch.js';
const props = defineProps({ modelValue: Boolean, availableSizes: { type: Array, default: () => [] }, sizeLabels: { type: Object, default: () => ({}) }, store: { type: Object, required: true } });
const emit = defineEmits(['update:modelValue', 'save']);
const localRatios = ref({});
watch(() => props.modelValue, open => { if (open) localRatios.value = Object.fromEntries(props.availableSizes.map(size => [size, props.store.curveMultiplier(size)])); });
const close = () => emit('update:modelValue', false);
const selectAll = () => { localRatios.value = Object.fromEntries(props.availableSizes.map(size => [size, 1])); };
const touch = createCellTouch({
  isDisabled: () => !props.modelValue,
  onTap: item => { localRatios.value = { ...localRatios.value, [item.id]: Math.min(99, Number(localRatios.value[item.id] || 0) + 1) }; },
  onEdit: input => { input?.focus(); input?.select(); },
});
onUnmounted(() => touch.cancel());
function setMultiplier(size, event) {
  const value = Math.max(0, Math.min(99, Math.trunc(Number(event.target.value) || 0)));
  localRatios.value = { ...localRatios.value, [size]: value };
  event.target.value = String(value);
}
const totalUnits = computed(() => props.availableSizes.reduce((sum, size) => sum + (Number(localRatios.value[size]) || 0), 0));
const ratioChain = computed(() => props.availableSizes.map(size => localRatios.value[size] || 0).join(' · '));
function save() { if (!totalUnits.value) return; emit('save', localRatios.value); close(); }
</script>

<style scoped>
.curve-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.eyebrow { color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: .08em; }
h3 { margin: 5px 0 0; color: #0f172a; font: inherit; font-size: 20px; font-weight: 700; }
.close-button { display: grid; place-items: center; width: 40px; height: 40px; padding: 0; border: 0; border-radius: var(--b2b-radius, 8px); background: #f1f5f9; color: #475569; cursor: pointer; }
.description { margin: 16px 0; color: #64748b; font-size: 13px; line-height: 1.6; }
.ratio-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(62px, 1fr)); gap: 7px; }
.ratio-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; text-align: center; color: #475569; font-size: 11px; font-weight: 700; }
.ratio-field input { box-sizing: border-box; width: 100%; height: 48px; padding: 0 4px; border: 1px solid #dfe4ee; border-radius: var(--b2b-radius-sm, 6px); background: #f7f8fc; color: #0f172a; font: inherit; font-size: 16px; font-weight: 700; text-align: center; appearance: textfield; -moz-appearance: textfield; }
.ratio-field input::-webkit-inner-spin-button, .ratio-field input::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
.ratio-field input:focus { outline: 2px solid var(--b2b-primary); outline-offset: 1px; background: #fff; }
.curve-preview { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; min-height: 38px; margin: 16px 0; padding: 8px 12px; border-radius: var(--b2b-radius, 8px); background: var(--b2b-primary-soft); color: #334155; font-size: 12px; }
.curve-preview span { margin-left: auto; font-variant-numeric: tabular-nums; color: var(--b2b-primary); }
.curve-preview.empty { color: #b91c1c; background: #fef2f2; }
.curve-footer, .footer-actions { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.curve-footer { padding-top: 16px; border-top: 1px solid #e2e8f0; }
@media (max-width: 480px) { .ratio-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } .footer-actions { margin-left: auto; } }
</style>
