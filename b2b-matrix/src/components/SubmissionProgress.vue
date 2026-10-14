<template>
  <DialogModal
    :open="open"
    label="Καταχώριση προπαραγγελίας"
    @close="!submitting && !redirecting && $emit('close')"
  >
    <div class="submission-progress">
      <span class="submission-pill">
        <UiIcon :name="submitError ? 'alert' : 'clock'" />
        {{
          submitError
            ? 'Η καταχώριση σταμάτησε'
            : redirecting
              ? 'Μετάβαση στις παραγγελίες'
              : finalizing
                ? 'Ολοκλήρωση παραγγελίας'
                : 'Σε εξέλιξη'
        }}
      </span>

      <h2>Καταχώριση προπαραγγελίας</h2>
      <p class="submission-instruction">
        Κρατήστε αυτό το παράθυρο ανοιχτό μέχρι να ολοκληρωθεί η καταχώριση.
      </p>
      <p class="submission-help">
        Αν διακοπεί η σύνδεση, μπορείτε να συνεχίσετε από το ίδιο σημείο.
      </p>

      <ol class="submission-stages" aria-label="Στάδια καταχώρισης">
        <li
          v-for="(stage, index) in submissionStages"
          :key="stage"
          :class="{ done: submissionStage > index, current: submissionStage === index }"
          :aria-current="submissionStage === index ? 'step' : undefined"
        >
          <span>{{ submissionStage > index ? '✓' : index + 1 }}</span>
          {{ stage }}
        </li>
      </ol>

      <div class="submission-meter">
        <div class="submission-counter" role="status" aria-live="polite">
          <span v-if="redirecting">
            Η παραγγελία καταχωρίστηκε. Μεταφέρεστε στις παραγγελίες σας…
          </span>
          <span v-else-if="finalizing">
            Υπολογισμός συνόλων και ολοκλήρωση παραγγελίας…
          </span>
          <span v-else-if="submissionStage === 1 && progress?.total">
            {{ progress.processed }} / {{ progress.total }} γραμμές παραγγελίας
          </span>
          <span v-else>
            Αποθήκευση σχολίου και προετοιμασία λίστας…
          </span>
          <strong v-if="submissionStage === 1 || progress?.complete">
            {{ progressPercent }}%
          </strong>
        </div>

        <div
          class="b2b-progress"
          :class="{
            'is-indeterminate': !submitError && !progress?.complete && submissionStage !== 1,
            'is-stopped': !!submitError,
          }"
          role="progressbar"
          aria-label="Πρόοδος τρέχοντος σταδίου"
          :aria-valuemin="submissionStage === 1 || progress?.complete ? 0 : undefined"
          :aria-valuemax="submissionStage === 1 || progress?.complete ? 100 : undefined"
          :aria-valuenow="
            submissionStage === 1 || progress?.complete ? progressPercent : undefined
          "
          :aria-valuetext="submissionStages[submissionStage]"
        >
          <span
            :style="{
              width: (progress?.complete ? 100 : submissionStage === 1 ? progressPercent : 35) + '%',
            }"
          ></span>
        </div>

        <p v-if="finalizing && progress?.total" class="submission-lines-done">
          Καταχώριση γραμμών: {{ progress.processed }} / {{ progress.total }} ·
          {{ progressPercent }}%
        </p>
      </div>

      <template v-if="submitError">
        <p role="alert" class="submission-error">{{ submitError }}</p>
        <div class="submission-actions">
          <B2BButton variant="outline" @click="$emit('close')">Κλείσιμο</B2BButton>
          <B2BButton icon="refresh" @click="$emit('retry')">Συνέχιση</B2BButton>
        </div>
      </template>
    </div>
  </DialogModal>
</template>

<script setup>
import DialogModal from './DialogModal.vue';
import UiIcon from './UiIcon.vue';
import B2BButton from './ui/B2BButton.vue';

defineProps({
  open: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  redirecting: { type: Boolean, default: false },
  finalizing: { type: Boolean, default: false },
  submitError: { type: String, default: '' },
  submissionStages: { type: Array, required: true },
  submissionStage: { type: Number, default: 0 },
  progress: { type: Object, default: null },
  progressPercent: { type: Number, default: 0 },
});

defineEmits(['close', 'retry']);
</script>

<style scoped>
.submission-progress h2 {
  margin: 16px 0 10px !important;
  color: #0f172a;
  font-size: 22px;
  line-height: 1.3;
  font-weight: 700;
  letter-spacing: -0.025em;
}

.submission-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 11px;
  border-radius: var(--b2b-radius-pill, 999px);
  background: var(--b2b-primary-soft);
  border: 1px solid var(--b2b-primary-border);
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 11px;
  font-weight: 700;
  line-height: 1.4;
}

.submission-pill :deep(svg) {
  width: 15px;
  height: 15px;
  flex-basis: 15px;
}

.submission-instruction {
  margin: 0 !important;
  color: #334155;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.65;
}

.submission-help {
  margin: 8px 0 0 !important;
  color: #64748b;
  font-size: 12px;
  line-height: 1.65;
}

.submission-stages {
  list-style: none !important;
  display: grid;
  gap: 10px;
  padding: 0 !important;
  margin: 20px 0 !important;
}

.submission-stages li {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #64748b;
  font-size: 13px;
}

.submission-stages li > span {
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: #f4f4f5;
  font-size: 12px;
  font-weight: 700;
}

.submission-stages li.current {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-weight: 700;
}

.submission-stages li.current > span,
.submission-stages li.done > span {
  background: var(--b2b-primary);
  color: var(--b2b-on-primary, #ffffff);
}

.submission-meter {
  margin-top: 24px;
  padding: 16px;
  border: 1px solid #e2e8f0;
  border-radius: var(--b2b-radius, 8px);
  background: #f8fafc;
}

.submission-counter {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-bottom: 12px;
  color: #64748b;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.submission-counter strong {
  color: var(--b2b-primary-ink, var(--b2b-primary));
  font-size: 15px;
}

.b2b-progress {
  display: block;
  width: 100%;
  height: 8px;
  border: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #e4e4e7;
}

.b2b-progress > span {
  display: block;
  height: 100%;
  background: var(--b2b-primary);
  transition: width 0.2s ease;
}

.b2b-progress.is-indeterminate > span {
  animation: b2b-submission-busy 1.4s ease-in-out infinite;
}

.b2b-progress.is-stopped > span {
  opacity: 0.45;
}

@keyframes b2b-submission-busy {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(290%);
  }
}

.submission-lines-done {
  color: #64748b;
  font-size: 12px;
  line-height: 1.5;
  margin: 8px 0 0 !important;
}

.submission-error {
  margin: 16px 0 0 !important;
  color: #be123c;
  font-size: 13px;
  line-height: 1.6;
}

.submission-actions {
  display: flex;
  justify-content: flex-end;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 20px;
}

@media (prefers-reduced-motion: reduce) {
  .b2b-progress > span {
    transition: none;
  }
  .b2b-progress.is-indeterminate > span {
    animation: none;
    width: 100% !important;
    opacity: 0.65;
  }
}

@media (max-width: 620px) {
  .submission-progress h2 {
    font-size: 20px;
  }
}
</style>
