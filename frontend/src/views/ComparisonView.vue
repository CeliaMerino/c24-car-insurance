<script setup lang="ts">
import { computed, nextTick, onMounted, watch, type Component } from 'vue'
import CoverageStep from '../components/form/CoverageStep.vue'
import DriverStep from '../components/form/DriverStep.vue'
import VehicleStep from '../components/form/VehicleStep.vue'
import OfferList from '../components/results/OfferList.vue'
import OfferSkeleton from '../components/results/OfferSkeleton.vue'
import EmptyResults from '../components/states/EmptyResults.vue'
import RequestError from '../components/states/RequestError.vue'
import RestoreNotice from '../components/states/RestoreNotice.vue'
import { useComparison } from '../composables/useComparison'
import { useFormStorage } from '../composables/useFormStorage'
import {
  hasAnyValue,
  hasFieldErrors,
  pickFormValues,
  steps,
  useQuoteForm,
  type FieldName,
  type StepId,
} from '../composables/useQuoteForm'

export type ViewState =
  | 'empty-form'
  | 'restored-form'
  | 'invalid-form'
  | 'loading'
  | 'full-results'
  | 'partial-results'
  | 'no-results'
  | 'request-error'

const stepComponents: Record<StepId, Component> = {
  driver: DriverStep,
  vehicle: VehicleStep,
  coverage: CoverageStep,
}

const {
  form,
  validateField,
  validateAll,
  applyServerErrors,
  focusFirstInvalid,
  hydrate,
  reset,
  dismissRestoreNotice,
  toRequest,
} = useQuoteForm()

const storage = useFormStorage()
const comparison = useComparison()

let skipPersist = false

onMounted(() => {
  const restored = storage.read()
  if (restored === null) {
    return
  }

  skipPersist = true
  hydrate(restored)
  form.restoreNoticeVisible = true
  void nextTick(() => {
    skipPersist = false
  })
})

watch(
  [
    () => form.dateOfBirth,
    () => form.postalCode,
    () => form.carCategory,
    () => form.usage,
    () => form.annualMileage,
    () => form.garage,
    () => form.coverage,
  ],
  () => {
    if (skipPersist) {
      return
    }

    const values = pickFormValues(form)
    if (hasAnyValue(values)) {
      storage.write(values)
    } else {
      storage.clear()
    }
  },
)

const viewState = computed((): ViewState => {
  if (comparison.state.status === 'loading') {
    return 'loading'
  }
  if (comparison.state.status === 'error') {
    return 'request-error'
  }
  if (comparison.state.status === 'empty') {
    return 'no-results'
  }
  if (comparison.state.status === 'success') {
    return comparison.allPartnersOk() ? 'full-results' : 'partial-results'
  }
  if (form.submitAttempted && hasFieldErrors(form.errors)) {
    return 'invalid-form'
  }
  if (form.restoreNoticeVisible) {
    return 'restored-form'
  }
  return 'empty-form'
})

const showForm = computed(() => {
  return (
    viewState.value === 'empty-form'
    || viewState.value === 'restored-form'
    || viewState.value === 'invalid-form'
    || viewState.value === 'request-error'
  )
})

async function onSubmit(): Promise<void> {
  if (!validateAll()) {
    focusFirstInvalid()
    return
  }

  await send(toRequest())
}

async function send(request: ReturnType<typeof toRequest>): Promise<void> {
  await comparison.submit(request)
  if (comparison.state.fieldErrors.length > 0) {
    applyServerErrors(comparison.state.fieldErrors)
    focusFirstInvalid()
  }
}

async function onRetry(): Promise<void> {
  if (!validateAll()) {
    comparison.returnToForm()
    focusFirstInvalid()
    return
  }

  await send(toRequest())
}

function onBlur(field: FieldName): void {
  validateField(field)
}

function onClearSaved(): void {
  skipPersist = true
  storage.clear()
  reset()
  void nextTick(() => {
    skipPersist = false
  })
}
</script>

<template>
  <main class="page" :data-state="viewState">
    <header class="page__header">
      <h1 class="page__title">Compare car insurance</h1>
      <p class="page__lead">
        Enter your details once to see annual prices from several insurers.
      </p>
    </header>

    <RestoreNotice
      v-if="viewState === 'restored-form'"
      @dismiss="dismissRestoreNotice"
      @clear="onClearSaved"
    />

    <form v-if="showForm" class="quote-form" novalidate @submit.prevent="onSubmit">
      <component
        :is="stepComponents[step.id]"
        v-for="step in steps"
        :key="step.id"
        :form="form"
        @blur="onBlur"
      />
      <div class="quote-form__submit">
        <button type="submit" class="button">Compare offers</button>
      </div>
    </form>

    <RequestError
      v-if="viewState === 'request-error'"
      :message="comparison.state.errorMessage"
      @retry="onRetry"
    />

    <OfferSkeleton v-if="viewState === 'loading'" />

    <template v-if="viewState === 'full-results' || viewState === 'partial-results'">
      <OfferList
        v-if="comparison.state.comparison !== null"
        :coverage="comparison.state.comparison.coverage"
        :offers="comparison.state.comparison.offers"
      />
      <button type="button" class="button button--secondary" @click="comparison.returnToForm">
        Change details
      </button>
    </template>

    <EmptyResults
      v-if="viewState === 'no-results'"
      @retry="onRetry"
      @change-details="comparison.returnToForm"
    />
  </main>
</template>
