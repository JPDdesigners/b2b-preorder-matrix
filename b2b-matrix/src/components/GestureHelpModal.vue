<template>
  <DialogModal
    :open="modelValue"
    label="Οδηγίες γρήγορης καταχώρισης"
    @close="close"
  >
    <div class="gesture-help-modal">
      <header class="gesture-help-header">
        <div class="gesture-help-title-row">
          <span class="gesture-help-badge" aria-hidden="true">
            <UiIcon name="info" />
          </span>
          <div>
            <h2>Οδηγίες γρήγορης καταχώρισης</h2>
            <p>Συντομεύσεις αφής, ποντικιού και καμπύλης στον πίνακα μεγεθών</p>
          </div>
        </div>
        <button
          type="button"
          class="gesture-help-close"
          aria-label="Κλείσιμο οδηγιών"
          @click="close"
        >
          <UiIcon name="close" />
        </button>
      </header>

      <div class="gesture-cards">
        <article class="gesture-card">
          <div class="gesture-card-icon">
            <UiIcon name="touch" />
          </div>
          <div class="gesture-card-body">
            <div class="gesture-card-top">
              <h3>Απλό πάτημα (Tap)</h3>
              <span class="gesture-tag">Tablet &amp; Κινητό</span>
            </div>
            <p>
              Πατήστε στιγμιαία πάνω σε οποιοδήποτε κελί μεγέθους για να προσθέσετε
              <strong>+1 τεμάχιο</strong> χωρίς να ανοίξει το πληκτρολόγιο.
            </p>
          </div>
        </article>

        <article class="gesture-card">
          <div class="gesture-card-icon">
            <UiIcon name="keyboard" />
          </div>
          <div class="gesture-card-body">
            <div class="gesture-card-top">
              <h3>Παρατεταμένο πάτημα (Κράτημα)</h3>
              <span class="gesture-tag">Tablet &amp; Κινητό</span>
            </div>
            <p>
              Κρατήστε πατημένο το κελί για <strong>0,5 δευτερόλεπτο</strong> ώστε να επιλεγεί
              το πεδίο και να πληκτρολογήσετε ακριβή αριθμό τεμαχίων.
            </p>
          </div>
        </article>

        <article class="gesture-card">
          <div class="gesture-card-icon">
            <UiIcon name="mouse" />
          </div>
          <div class="gesture-card-body">
            <div class="gesture-card-top">
              <h3>Ροδέλα ποντικιού</h3>
              <span class="gesture-tag">Desktop</span>
            </div>
            <p>
              Τοποθετήστε τον δείκτη πάνω σε ένα κελί και κυλήστε τη ροδέλα
              <strong>πάνω (+1)</strong> ή <strong>κάτω (−1)</strong> για άμεση αυξομείωση.
            </p>
          </div>
        </article>

        <article class="gesture-card">
          <div class="gesture-card-icon">
            <UiIcon name="sliders" />
          </div>
          <div class="gesture-card-body">
            <div class="gesture-card-top">
              <h3>Καμπύλη μεγεθών (− / +)</h3>
              <span class="gesture-tag">Όλες οι συσκευές</span>
            </div>
            <p>
              Χρησιμοποιήστε τα κουμπιά <strong>−</strong> και <strong>+</strong> δίπλα σε κάθε
              χρώμα για να αυξομειώσετε ολόκληρη τη σειρά μεγεθών με ένα πάτημα.
            </p>
          </div>
        </article>
      </div>

      <footer class="gesture-help-footer">
        <B2BButton variant="primary" size="md" @click="close">
          Κατάλαβα
        </B2BButton>
      </footer>
    </div>
  </DialogModal>
</template>

<script setup>
import DialogModal from './DialogModal.vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';

defineProps({
  modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function close() {
  emit('update:modelValue', false);
}
</script>

<style scoped>
.gesture-help-modal {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.gesture-help-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 14px;
  border-bottom: 1px solid #e2e8f0;
}

.gesture-help-title-row {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
}

.gesture-help-badge {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border-radius: var(--b2b-radius, 8px);
  background: var(--b2b-primary-soft, #eef2ff);
  border: 1px solid var(--b2b-primary-border, #c7d2fe);
  color: var(--b2b-primary-ink, var(--b2b-primary, #4f46e5));
}

.gesture-help-header h2 {
  margin: 0;
  font-size: 17px;
  font-weight: 700;
  color: #0f172a;
  line-height: 1.3;
}

.gesture-help-header p {
  margin: 3px 0 0;
  font-size: 12px;
  color: #64748b;
  line-height: 1.4;
}

.gesture-help-close {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  min-height: 36px !important;
  flex: 0 0 36px;
  padding: 0;
  border: 1px solid transparent;
  border-radius: var(--b2b-radius, 8px);
  background: transparent;
  color: #64748b;
  cursor: pointer;
  transition: all 0.15s ease;
}

.gesture-help-close:hover {
  background: #f1f5f9;
  color: #0f172a;
}

.gesture-cards {
  display: grid;
  gap: 10px;
}

.gesture-card {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius, 8px);
  background: #f8fafc;
}

.gesture-card-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  border-radius: var(--b2b-radius-sm, 6px);
  background: #ffffff;
  border: 1px solid #e2e8f0;
  color: var(--b2b-primary-ink, var(--b2b-primary, #4f46e5));
}

.gesture-card-body {
  flex: 1;
  min-width: 0;
}

.gesture-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 4px;
}

.gesture-card-top h3 {
  margin: 0;
  font-size: 13px;
  font-weight: 700;
  color: #0f172a;
}

.gesture-tag {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: var(--b2b-radius-pill, 999px);
  background: #ffffff;
  border: 1px solid #cbd5e1;
  color: #475569;
  font-size: 10px;
  font-weight: 600;
}

.gesture-card-body p {
  margin: 0;
  font-size: 12px;
  line-height: 1.5;
  color: #475569;
}

.gesture-card-body strong {
  color: #0f172a;
  font-weight: 600;
}

.gesture-help-footer {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}
</style>
