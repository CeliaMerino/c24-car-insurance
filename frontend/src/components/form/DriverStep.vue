<script setup lang="ts">
import type { QuoteFormState } from '../../composables/useQuoteForm'
import FormField from './FormField.vue'

defineProps<{
  form: QuoteFormState
}>()

const emit = defineEmits<{
  blur: [field: 'dateOfBirth' | 'postalCode']
}>()
</script>

<template>
  <fieldset class="step">
    <legend class="step__title">Driver</legend>
    <div class="step__fields">
      <FormField id="dateOfBirth" label="Date of birth" :error="form.errors.dateOfBirth">
        <input
          id="dateOfBirth"
          v-model="form.dateOfBirth"
          class="input"
          type="date"
          name="dateOfBirth"
          autocomplete="bday"
          :aria-invalid="form.errors.dateOfBirth !== ''"
          :aria-describedby="form.errors.dateOfBirth !== '' ? 'dateOfBirth-error' : undefined"
          @blur="emit('blur', 'dateOfBirth')"
        />
      </FormField>
      <FormField id="postalCode" label="Postal code" :error="form.errors.postalCode">
        <input
          id="postalCode"
          v-model="form.postalCode"
          class="input"
          type="text"
          name="postalCode"
          inputmode="numeric"
          maxlength="5"
          autocomplete="postal-code"
          placeholder="28013"
          :aria-invalid="form.errors.postalCode !== ''"
          :aria-describedby="form.errors.postalCode !== '' ? 'postalCode-error' : undefined"
          @blur="emit('blur', 'postalCode')"
        />
      </FormField>
    </div>
  </fieldset>
</template>
