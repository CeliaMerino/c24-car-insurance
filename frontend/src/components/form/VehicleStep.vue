<script setup lang="ts">
import type { QuoteFormState } from '../../composables/useQuoteForm'
import FormField from './FormField.vue'
import { CAR_CATEGORY_OPTIONS, GARAGE_OPTIONS, MILEAGE_OPTIONS, USAGE_OPTIONS } from './options'

defineProps<{
  form: QuoteFormState
}>()

const emit = defineEmits<{
  blur: [field: 'carCategory' | 'usage' | 'annualMileage' | 'garage']
}>()
</script>

<template>
  <fieldset class="step">
    <legend class="step__title">Vehicle</legend>
    <div class="step__fields">
      <FormField id="carCategory" label="Car category" :error="form.errors.carCategory">
        <select
          id="carCategory"
          v-model="form.carCategory"
          class="input"
          name="carCategory"
          :aria-invalid="form.errors.carCategory !== ''"
          :aria-describedby="form.errors.carCategory !== '' ? 'carCategory-error' : undefined"
          @blur="emit('blur', 'carCategory')"
        >
          <option disabled value="">Select a category</option>
          <option v-for="option in CAR_CATEGORY_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </FormField>

      <FormField id="annualMileage" label="Annual mileage" :error="form.errors.annualMileage">
        <select
          id="annualMileage"
          v-model="form.annualMileage"
          class="input"
          name="annualMileage"
          :aria-invalid="form.errors.annualMileage !== ''"
          :aria-describedby="form.errors.annualMileage !== '' ? 'annualMileage-error' : undefined"
          @blur="emit('blur', 'annualMileage')"
        >
          <option disabled value="">Select mileage</option>
          <option v-for="option in MILEAGE_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </FormField>

      <fieldset class="choice-group" :aria-invalid="form.errors.usage !== ''">
        <legend class="field__label">Usage</legend>
        <div class="choice-group__options">
          <label v-for="option in USAGE_OPTIONS" :key="option.value" class="choice">
            <input
              :id="option.value === 'private' ? 'usage' : `usage-${option.value}`"
              v-model="form.usage"
              type="radio"
              name="usage"
              :value="option.value"
              @blur="emit('blur', 'usage')"
            />
            {{ option.label }}
          </label>
        </div>
        <p v-if="form.errors.usage !== ''" id="usage-error" class="field__error" role="alert">
          {{ form.errors.usage }}
        </p>
      </fieldset>

      <fieldset class="choice-group" :aria-invalid="form.errors.garage !== ''">
        <legend class="field__label">Private garage</legend>
        <div class="choice-group__options">
          <label v-for="option in GARAGE_OPTIONS" :key="String(option.value)" class="choice">
            <input
              :id="option.value === true ? 'garage' : 'garage-no'"
              type="radio"
              name="garage"
              :checked="form.garage === option.value"
              @change="form.garage = option.value"
              @blur="emit('blur', 'garage')"
            />
            {{ option.label }}
          </label>
        </div>
        <p v-if="form.errors.garage !== ''" id="garage-error" class="field__error" role="alert">
          {{ form.errors.garage }}
        </p>
      </fieldset>
    </div>
  </fieldset>
</template>
