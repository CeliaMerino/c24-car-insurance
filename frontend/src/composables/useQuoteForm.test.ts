import { describe, expect, it } from 'vitest'
import {
  API_TO_FIELD,
  FIELD_TO_API,
  fieldRules,
  steps,
  useQuoteForm,
  type FormValues,
} from './useQuoteForm'

const FROZEN_MS = Date.parse('2026-08-31T12:00:00Z')

function form(overrides: Partial<FormValues> = {}) {
  const { form: state, ...methods } = useQuoteForm({ now: () => FROZEN_MS })
  Object.assign(state, overrides)
  return { form: state, ...methods }
}

function validValues(): FormValues {
  return {
    dateOfBirth: '1990-05-14',
    postalCode: '28013',
    carCategory: 'sedan',
    usage: 'private',
    annualMileage: '5k_15k',
    garage: true,
    coverage: 'third_party_plus',
  }
}

describe('steps', () => {
  it('is the data structure from the architecture spec', () => {
    expect(steps).toEqual([
      { id: 'driver', fields: ['dateOfBirth', 'postalCode'] },
      { id: 'vehicle', fields: ['carCategory', 'usage', 'annualMileage', 'garage'] },
      { id: 'coverage', fields: ['coverage'] },
    ])
  })
})

describe('field rules', () => {
  it('rejects an empty form with a required error on every field', () => {
    const quote = form()

    expect(quote.validateAll()).toBe(false)
    expect(quote.form.errors).toEqual({
      dateOfBirth: 'Field "date_of_birth" is required.',
      postalCode: 'Field "postal_code" is required.',
      carCategory: 'Field "car_category" is required.',
      usage: 'Field "usage" is required.',
      annualMileage: 'Field "annual_mileage" is required.',
      garage: 'Field "garage" is required.',
      coverage: 'Field "coverage" is required.',
    })
    expect(quote.firstInvalidField()).toBe('dateOfBirth')
  })

  it('uses the same rule for blur and submit', () => {
    const quote = form({ postalCode: '12' })
    const fromRule = fieldRules.postalCode(quote.form, FROZEN_MS)

    quote.validateField('postalCode')
    const blurMessage = quote.form.errors.postalCode

    quote.form.errors.postalCode = ''
    quote.validateAll()

    expect(fromRule?.code).toBe('format')
    expect(blurMessage).toBe('Enter a five-digit postal code.')
    expect(quote.form.errors.postalCode).toBe(blurMessage)
  })

  it('rejects an underage date of birth', () => {
    const quote = form({ dateOfBirth: '2008-09-01' })

    expect(quote.validateField('dateOfBirth')).toBe(false)
    expect(quote.form.errors.dateOfBirth).toBe('You must be at least 18 years old.')
    expect(fieldRules.dateOfBirth(quote.form, FROZEN_MS)?.code).toBe('min_age')
  })

  it('accepts an 18-year-old on the frozen date', () => {
    const quote = form({ dateOfBirth: '2008-08-31' })

    expect(quote.validateField('dateOfBirth')).toBe(true)
    expect(quote.form.errors.dateOfBirth).toBe('')
  })

  it('rejects a future date of birth', () => {
    const quote = form({ dateOfBirth: '2026-09-01' })

    expect(quote.validateField('dateOfBirth')).toBe(false)
    expect(fieldRules.dateOfBirth(quote.form, FROZEN_MS)?.code).toBe('future_date')
    expect(quote.form.errors.dateOfBirth).toBe('Date of birth cannot be in the future.')
  })

  it('rejects a date that is not YYYY-MM-DD', () => {
    const quote = form({ dateOfBirth: '31/08/1990' })

    expect(quote.validateField('dateOfBirth')).toBe(false)
    expect(fieldRules.dateOfBirth(quote.form, FROZEN_MS)?.code).toBe('format')
  })

  it('rejects a calendar-impossible date', () => {
    const quote = form({ dateOfBirth: '2020-02-30' })

    expect(quote.validateField('dateOfBirth')).toBe(false)
    expect(fieldRules.dateOfBirth(quote.form, FROZEN_MS)?.code).toBe('format')
  })

  it('accepts Spanish postal codes from 01000 to 52999', () => {
    const low = form({ postalCode: '01000' })
    const high = form({ postalCode: '52999' })
    const madrid = form({ postalCode: '28013' })

    expect(low.validateField('postalCode')).toBe(true)
    expect(high.validateField('postalCode')).toBe(true)
    expect(madrid.validateField('postalCode')).toBe(true)
  })

  it('rejects a postal code that is not five digits in range', () => {
    const short = form({ postalCode: '12' })
    const below = form({ postalCode: '00999' })
    const above = form({ postalCode: '53000' })

    expect(short.validateField('postalCode')).toBe(false)
    expect(below.validateField('postalCode')).toBe(false)
    expect(above.validateField('postalCode')).toBe(false)
    expect(fieldRules.postalCode(short.form, FROZEN_MS)?.code).toBe('format')
  })

  it('rejects an unknown enum value', () => {
    const quote = form({ carCategory: 'spaceship' as FormValues['carCategory'] })

    expect(quote.validateField('carCategory')).toBe(false)
    expect(fieldRules.carCategory(quote.form, FROZEN_MS)?.code).toBe('unknown_value')
    expect(quote.form.errors.carCategory).toBe('Field "car_category" is not a recognised value.')
  })

  it('validates only the fields of the requested step', () => {
    const quote = form({
      dateOfBirth: '1990-05-14',
      postalCode: '28013',
    })

    expect(quote.validateStep('driver')).toBe(true)
    expect(quote.form.errors.carCategory).toBe('')
    expect(quote.validateStep('vehicle')).toBe(false)
    expect(quote.form.errors.carCategory).toBe('Field "car_category" is required.')
    expect(quote.form.errors.coverage).toBe('')
  })

  it('accepts a complete valid form', () => {
    const quote = form(validValues())

    expect(quote.validateAll()).toBe(true)
    expect(quote.toRequest()).toEqual({
      dateOfBirth: '1990-05-14',
      postalCode: '28013',
      carCategory: 'sedan',
      usage: 'private',
      annualMileage: '5k_15k',
      garage: true,
      coverage: 'third_party_plus',
    })
  })
})

describe('server field errors', () => {
  it('maps snake_case API fields onto the same form fields', () => {
    const quote = form(validValues())

    quote.applyServerErrors([
      {
        field: 'date_of_birth',
        code: 'min_age',
        message: 'You must be at least 18 years old.',
      },
      {
        field: 'postal_code',
        code: 'format',
        message: 'Enter a five-digit postal code.',
      },
      {
        field: 'driver_name',
        code: 'unknown_field',
        message: 'Field "driver_name" is not in the contract.',
      },
    ])

    expect(quote.form.errors.dateOfBirth).toBe('You must be at least 18 years old.')
    expect(quote.form.errors.postalCode).toBe('Enter a five-digit postal code.')
    expect(quote.form.errors.carCategory).toBe('')
    expect(quote.firstInvalidField()).toBe('dateOfBirth')
    expect(API_TO_FIELD.date_of_birth).toBe('dateOfBirth')
    expect(FIELD_TO_API.dateOfBirth).toBe('date_of_birth')
  })
})
