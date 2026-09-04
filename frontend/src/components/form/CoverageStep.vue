<script setup lang="ts">
import type { QuoteFormState } from '../../composables/useQuoteForm'
import { COVERAGE_OPTIONS } from './options'

defineProps<{
  form: QuoteFormState
}>()

const emit = defineEmits<{
  blur: [field: 'coverage']
}>()
</script>

<template>
  <fieldset class="step">
    <legend class="step__title">Coverage</legend>
    <p v-if="form.errors.coverage !== ''" id="coverage-error" class="field__error" role="alert">
      {{ form.errors.coverage }}
    </p>
    <div class="coverage-cards" role="radiogroup" aria-labelledby="coverage-heading">
      <span id="coverage-heading" class="visually-hidden">Coverage level</span>
      <label
        v-for="(option, index) in COVERAGE_OPTIONS"
        :key="option.value"
        class="coverage-card"
        :class="{ 'coverage-card--selected': form.coverage === option.value }"
      >
        <input
          :id="index === 0 ? 'coverage' : `coverage-${option.value}`"
          v-model="form.coverage"
          class="coverage-card__input"
          type="radio"
          name="coverage"
          :value="option.value"
          @blur="emit('blur', 'coverage')"
        />
        <span class="coverage-card__title">{{ option.title }}</span>
        <span class="coverage-card__meaning">{{ option.meaning }}</span>
      </label>
    </div>
  </fieldset>
</template>
