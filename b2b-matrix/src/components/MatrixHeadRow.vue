<template>
  <tr>
    <th scope="col" class="sticky-col color-heading" :class="{ scrolled }">Χρώμα <span>{{ store.data.colors.length }}</span></th>
    <th v-if="!readonly && showRowActions" scope="col" class="curve-heading">Καμπύλη</th>
    <th
      v-for="size in store.data.sizes"
      :key="size"
      scope="col"
      :class="{
        'selected-size': store.isCustomCurveActive && store.activeCurveSizes.includes(size),
        'excluded-size': store.isCustomCurveActive && !store.activeCurveSizes.includes(size)
      }"
    >
      {{ store.sizeLabel(size) }}
      <span
        v-if="store.isCustomCurveActive && store.activeCurveSizes.includes(size)"
        class="size-dot"
        :title="store.curveMultiplier(size) > 1 ? `Σειρά: x${store.curveMultiplier(size)}` : 'Στην επιλεγμένη σειρά'"
        aria-label="Στην επιλεγμένη σειρά"
      ></span>
    </th>
  </tr>
</template>

<script setup>
// One size-header row, rendered both in the table (semantics/layout) and in the docked copy.
defineProps({
  store: { type: Object, required: true },
  readonly: Boolean,
  showRowActions: Boolean,
  scrolled: Boolean,
});
</script>
