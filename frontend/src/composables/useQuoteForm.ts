import { reactive } from 'vue'
import type {
  AnnualMileage,
  CarCategory,
  ComparisonRequest,
  Coverage,
  Usage,
  ValidationCode,
  ValidationFieldError,
} from '../api/types'

export type FieldName =
  | 'dateOfBirth'
  | 'postalCode'
  | 'carCategory'
  | 'usage'
  | 'annualMileage'
  | 'garage'
  | 'coverage'

export type StepId = 'driver' | 'vehicle' | 'coverage'

export type FormStep = {
  id: StepId
  fields: FieldName[]
}

export type FormValues = {
  dateOfBirth: string
  postalCode: string
  carCategory: CarCategory | ''
  usage: Usage | ''
  annualMileage: AnnualMileage | ''
  garage: boolean | null
  coverage: Coverage | ''
}

export type FieldIssue = {
  code: ValidationCode
  message: string
}

export type QuoteFormState = FormValues & {
  errors: Record<FieldName, string>
  restoreNoticeVisible: boolean
  submitAttempted: boolean
}

export const steps: FormStep[] = [
  { id: 'driver', fields: ['dateOfBirth', 'postalCode'] },
  { id: 'vehicle', fields: ['carCategory', 'usage', 'annualMileage', 'garage'] },
  { id: 'coverage', fields: ['coverage'] },
]

export const FIELD_ORDER: FieldName[] = steps.flatMap((step) => step.fields)

export const FIELD_TO_API: Record<FieldName, string> = {
  dateOfBirth: 'date_of_birth',
  postalCode: 'postal_code',
  carCategory: 'car_category',
  usage: 'usage',
  annualMileage: 'annual_mileage',
  garage: 'garage',
  coverage: 'coverage',
}

export const API_TO_FIELD: Record<string, FieldName> = {
  date_of_birth: 'dateOfBirth',
  postal_code: 'postalCode',
  car_category: 'carCategory',
  usage: 'usage',
  annual_mileage: 'annualMileage',
  garage: 'garage',
  coverage: 'coverage',
}

const CAR_CATEGORIES: CarCategory[] = ['compact', 'sedan', 'suv', 'van']
const USAGES: Usage[] = ['private', 'commercial']
const MILEAGES: AnnualMileage[] = ['under_5k', '5k_15k', '15k_30k', 'over_30k']
const COVERAGES: Coverage[] = ['third_party', 'third_party_plus', 'comprehensive']

const POSTAL_MIN = 1000
const POSTAL_MAX = 52999
const MIN_AGE = 18

export const fieldRules: {
  [K in FieldName]: (values: FormValues, nowMs: number) => FieldIssue | null
} = {
  dateOfBirth: (values, nowMs) => dateOfBirthRule(values.dateOfBirth, nowMs),
  postalCode: (values) => postalCodeRule(values.postalCode),
  carCategory: (values) => enumRule('car_category', values.carCategory, CAR_CATEGORIES),
  usage: (values) => enumRule('usage', values.usage, USAGES),
  annualMileage: (values) => enumRule('annual_mileage', values.annualMileage, MILEAGES),
  garage: (values) => garageRule(values.garage),
  coverage: (values) => enumRule('coverage', values.coverage, COVERAGES),
}

export function emptyFormValues(): FormValues {
  return {
    dateOfBirth: '',
    postalCode: '',
    carCategory: '',
    usage: '',
    annualMileage: '',
    garage: null,
    coverage: '',
  }
}

export function emptyErrors(): Record<FieldName, string> {
  return {
    dateOfBirth: '',
    postalCode: '',
    carCategory: '',
    usage: '',
    annualMileage: '',
    garage: '',
    coverage: '',
  }
}

export function pickFormValues(state: FormValues): FormValues {
  return {
    dateOfBirth: state.dateOfBirth,
    postalCode: state.postalCode,
    carCategory: state.carCategory,
    usage: state.usage,
    annualMileage: state.annualMileage,
    garage: state.garage,
    coverage: state.coverage,
  }
}

export function hasAnyValue(values: FormValues): boolean {
  return (
    values.dateOfBirth !== ''
    || values.postalCode !== ''
    || values.carCategory !== ''
    || values.usage !== ''
    || values.annualMileage !== ''
    || values.garage !== null
    || values.coverage !== ''
  )
}

export function hasFieldErrors(errors: Record<FieldName, string>): boolean {
  return FIELD_ORDER.some((field) => errors[field] !== '')
}

export type QuoteFormOptions = {
  now?: () => number
}

export function useQuoteForm(options: QuoteFormOptions = {}) {
  const now = options.now ?? Date.now
  const form = reactive<QuoteFormState>({
    ...emptyFormValues(),
    errors: emptyErrors(),
    restoreNoticeVisible: false,
    submitAttempted: false,
  })

  function issueFor(field: FieldName): FieldIssue | null {
    return fieldRules[field](pickFormValues(form), now())
  }

  function validateField(field: FieldName): boolean {
    const issue = issueFor(field)
    form.errors[field] = issue === null ? '' : issue.message
    return issue === null
  }

  function validateStep(stepId: StepId): boolean {
    const step = steps.find((candidate) => candidate.id === stepId)
    if (step === undefined) {
      return true
    }

    let valid = true
    for (const field of step.fields) {
      if (!validateField(field)) {
        valid = false
      }
    }

    return valid
  }

  function validateAll(): boolean {
    form.submitAttempted = true
    let valid = true
    for (const field of FIELD_ORDER) {
      if (!validateField(field)) {
        valid = false
      }
    }

    return valid
  }

  function applyServerErrors(errors: ValidationFieldError[]): void {
    form.submitAttempted = true
    form.errors = emptyErrors()
    for (const error of errors) {
      const field = API_TO_FIELD[error.field]
      if (field === undefined) {
        continue
      }
      if (form.errors[field] === '') {
        form.errors[field] = error.message
      }
    }
  }

  function firstInvalidField(): FieldName | null {
    for (const field of FIELD_ORDER) {
      if (form.errors[field] !== '') {
        return field
      }
    }

    return null
  }

  function focusFirstInvalid(): FieldName | null {
    const field = firstInvalidField()
    if (field === null) {
      return null
    }

    if (typeof document !== 'undefined') {
      document.getElementById(field)?.focus()
    }

    return field
  }

  function hydrate(values: FormValues): void {
    form.dateOfBirth = values.dateOfBirth
    form.postalCode = values.postalCode
    form.carCategory = values.carCategory
    form.usage = values.usage
    form.annualMileage = values.annualMileage
    form.garage = values.garage
    form.coverage = values.coverage
  }

  function reset(): void {
    const blanks = emptyFormValues()
    form.dateOfBirth = blanks.dateOfBirth
    form.postalCode = blanks.postalCode
    form.carCategory = blanks.carCategory
    form.usage = blanks.usage
    form.annualMileage = blanks.annualMileage
    form.garage = blanks.garage
    form.coverage = blanks.coverage
    form.errors = emptyErrors()
    form.restoreNoticeVisible = false
    form.submitAttempted = false
  }

  function dismissRestoreNotice(): void {
    form.restoreNoticeVisible = false
  }

  function toRequest(): ComparisonRequest {
    if (
      form.carCategory === ''
      || form.usage === ''
      || form.annualMileage === ''
      || form.garage === null
      || form.coverage === ''
    ) {
      throw new Error('toRequest() requires a valid form.')
    }

    return {
      dateOfBirth: form.dateOfBirth,
      postalCode: form.postalCode,
      carCategory: form.carCategory,
      usage: form.usage,
      annualMileage: form.annualMileage,
      garage: form.garage,
      coverage: form.coverage,
    }
  }

  return {
    form,
    steps,
    validateField,
    validateStep,
    validateAll,
    applyServerErrors,
    firstInvalidField,
    focusFirstInvalid,
    hydrate,
    reset,
    dismissRestoreNotice,
    toRequest,
  }
}

function required(apiField: string): FieldIssue {
  return {
    code: 'required',
    message: `Field "${apiField}" is required.`,
  }
}

function dateOfBirthRule(value: string, nowMs: number): FieldIssue | null {
  if (value === '') {
    return required('date_of_birth')
  }

  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return {
      code: 'format',
      message: 'Enter a valid date in YYYY-MM-DD format.',
    }
  }

  const [year, month, day] = value.split('-').map((part) => Number(part))
  if (!isRealDate(year, month, day)) {
    return {
      code: 'format',
      message: 'Enter a valid date in YYYY-MM-DD format.',
    }
  }

  const today = utcYmd(nowMs)
  if (isAfter(year, month, day, today.year, today.month, today.day)) {
    return {
      code: 'future_date',
      message: 'Date of birth cannot be in the future.',
    }
  }

  if (ageInYears(year, month, day, today.year, today.month, today.day) < MIN_AGE) {
    return {
      code: 'min_age',
      message: 'You must be at least 18 years old.',
    }
  }

  return null
}

function postalCodeRule(value: string): FieldIssue | null {
  if (value === '') {
    return required('postal_code')
  }

  if (!/^\d{5}$/.test(value)) {
    return {
      code: 'format',
      message: 'Enter a five-digit postal code.',
    }
  }

  const numeric = Number(value)
  if (numeric < POSTAL_MIN || numeric > POSTAL_MAX) {
    return {
      code: 'format',
      message: 'Enter a five-digit postal code.',
    }
  }

  return null
}

function enumRule<T extends string>(
  apiField: string,
  value: T | '',
  allowed: readonly T[],
): FieldIssue | null {
  if (value === '') {
    return required(apiField)
  }

  if (!allowed.includes(value)) {
    return {
      code: 'unknown_value',
      message: `Field "${apiField}" is not a recognised value.`,
    }
  }

  return null
}

function garageRule(value: boolean | null): FieldIssue | null {
  if (value === null) {
    return required('garage')
  }

  return null
}

function isRealDate(year: number, month: number, day: number): boolean {
  const date = new Date(Date.UTC(year, month - 1, day))
  return (
    date.getUTCFullYear() === year
    && date.getUTCMonth() === month - 1
    && date.getUTCDate() === day
  )
}

function utcYmd(nowMs: number): { year: number; month: number; day: number } {
  const date = new Date(nowMs)
  return {
    year: date.getUTCFullYear(),
    month: date.getUTCMonth() + 1,
    day: date.getUTCDate(),
  }
}

function isAfter(
  year: number,
  month: number,
  day: number,
  otherYear: number,
  otherMonth: number,
  otherDay: number,
): boolean {
  return (
    year > otherYear
    || (year === otherYear && month > otherMonth)
    || (year === otherYear && month === otherMonth && day > otherDay)
  )
}

function ageInYears(
  year: number,
  month: number,
  day: number,
  refYear: number,
  refMonth: number,
  refDay: number,
): number {
  let years = refYear - year
  if (refMonth < month || (refMonth === month && refDay < day)) {
    years -= 1
  }

  return years
}
