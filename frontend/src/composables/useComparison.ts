import { reactive } from 'vue'
import { createComparison, type ComparisonResult } from '../api/client'
import type { Comparison, ComparisonRequest, ValidationFieldError } from '../api/types'

export type ComparisonStatus = 'idle' | 'loading' | 'success' | 'empty' | 'error'

export type ComparisonState = {
  status: ComparisonStatus
  comparison: Comparison | null
  fieldErrors: ValidationFieldError[]
  errorMessage: string
}

export type ComparisonOptions = {
  submit?: (request: ComparisonRequest) => Promise<ComparisonResult>
}

const REQUEST_ERROR_MESSAGE = 'The comparison could not be completed. Please try again.'

export function useComparison(options: ComparisonOptions = {}) {
  const submitRequest = options.submit ?? createComparison
  const state = reactive<ComparisonState>({
    status: 'idle',
    comparison: null,
    fieldErrors: [],
    errorMessage: '',
  })

  let lastRequest: ComparisonRequest | null = null

  async function submit(request: ComparisonRequest): Promise<void> {
    lastRequest = request
    state.status = 'loading'
    state.fieldErrors = []
    state.errorMessage = ''
    state.comparison = null

    const result = await submitRequest(request)
    applyResult(result)
  }

  async function retry(): Promise<void> {
    if (lastRequest === null) {
      return
    }

    await submit(lastRequest)
  }

  function returnToForm(): void {
    state.status = 'idle'
    state.comparison = null
    state.fieldErrors = []
    state.errorMessage = ''
  }

  function applyResult(result: ComparisonResult): void {
    if (result.kind === 'ok') {
      state.comparison = result.comparison
      state.status = result.comparison.offers.length === 0 ? 'empty' : 'success'
      return
    }

    if (result.kind === 'validation') {
      state.fieldErrors = result.errors
      state.status = 'idle'
      return
    }

    state.status = 'error'
    state.errorMessage = REQUEST_ERROR_MESSAGE
  }

  function allPartnersOk(): boolean {
    if (state.comparison === null) {
      return false
    }

    const partners = state.comparison.partners
    return partners.length > 0 && partners.every((partner) => partner.status === 'ok')
  }

  return {
    state,
    submit,
    retry,
    returnToForm,
    allPartnersOk,
  }
}
