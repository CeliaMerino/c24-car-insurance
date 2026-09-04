import { beforeEach, describe, expect, it } from 'vitest'
import {
  FORM_STORAGE_KEY,
  FORM_STORAGE_TTL_MS,
  FORM_STORAGE_VERSION,
  useFormStorage,
} from './useFormStorage'
import { emptyFormValues, type FormValues } from './useQuoteForm'

const FROZEN_MS = Date.parse('2026-08-31T12:00:00Z')

function sampleValues(overrides: Partial<FormValues> = {}): FormValues {
  return {
    dateOfBirth: '1990-05-14',
    postalCode: '28013',
    carCategory: 'sedan',
    usage: 'private',
    annualMileage: '',
    garage: null,
    coverage: '',
    ...overrides,
  }
}

function storageAt(nowMs: number) {
  return useFormStorage({
    now: () => nowMs,
    storage: window.localStorage,
  })
}

describe('useFormStorage', () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it('writes the form object under the versioned key with a timestamp', () => {
    const storage = storageAt(FROZEN_MS)
    const values = sampleValues()

    storage.write(values)

    const raw = window.localStorage.getItem(FORM_STORAGE_KEY)
    expect(raw).not.toBeNull()
    expect(JSON.parse(raw ?? '')).toEqual({
      version: FORM_STORAGE_VERSION,
      savedAt: FROZEN_MS,
      values,
    })
  })

  it('restores values after a reload when the payload is fresh', () => {
    const writer = storageAt(FROZEN_MS)
    const values = sampleValues()
    writer.write(values)

    const reader = storageAt(FROZEN_MS + 60_000)
    expect(reader.read()).toEqual(values)
  })

  it('restores a partially filled form', () => {
    const storage = storageAt(FROZEN_MS)
    const values = sampleValues({
      dateOfBirth: '1988-01-02',
      postalCode: '08001',
      carCategory: 'suv',
      usage: 'commercial',
      annualMileage: 'over_30k',
    })
    storage.write(values)

    expect(storage.read()).toEqual(values)
  })

  it('does not restore input older than 24 hours', () => {
    const writer = storageAt(FROZEN_MS - FORM_STORAGE_TTL_MS - 60 * 60 * 1000)
    writer.write(sampleValues())

    const reader = storageAt(FROZEN_MS)
    expect(reader.read()).toBeNull()
    expect(window.localStorage.getItem(FORM_STORAGE_KEY)).not.toBeNull()
  })

  it('restores input that is still under 24 hours old', () => {
    const savedAt = FROZEN_MS - FORM_STORAGE_TTL_MS + 1
    const writer = storageAt(savedAt)
    const values = sampleValues()
    writer.write(values)

    const reader = storageAt(FROZEN_MS)
    expect(reader.read()).toEqual(values)
  })

  it('discards a payload that does not parse', () => {
    window.localStorage.setItem(FORM_STORAGE_KEY, '{')

    expect(storageAt(FROZEN_MS).read()).toBeNull()
  })

  it('discards a payload whose version does not match', () => {
    window.localStorage.setItem(
      FORM_STORAGE_KEY,
      JSON.stringify({
        version: 2,
        savedAt: FROZEN_MS,
        values: sampleValues(),
      }),
    )

    expect(storageAt(FROZEN_MS).read()).toBeNull()
  })

  it('discards a payload with the wrong shape', () => {
    window.localStorage.setItem(
      FORM_STORAGE_KEY,
      JSON.stringify({ version: 1, savedAt: FROZEN_MS, values: { postalCode: '28013' } }),
    )

    expect(storageAt(FROZEN_MS).read()).toBeNull()
  })

  it('does not restore an empty form and does not read a different key', () => {
    const storage = storageAt(FROZEN_MS)
    storage.write(emptyFormValues())
    window.localStorage.setItem('c24-comparison-form-v0', JSON.stringify({
      version: 0,
      savedAt: FROZEN_MS,
      values: sampleValues(),
    }))

    expect(storage.read()).toBeNull()
  })

  it('clears the versioned key', () => {
    const storage = storageAt(FROZEN_MS)
    storage.write(sampleValues())
    storage.clear()

    expect(window.localStorage.getItem(FORM_STORAGE_KEY)).toBeNull()
    expect(storage.read()).toBeNull()
  })
})
