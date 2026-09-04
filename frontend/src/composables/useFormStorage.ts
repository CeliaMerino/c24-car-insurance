import {
  hasAnyValue,
  type FormValues,
} from './useQuoteForm'

export const FORM_STORAGE_KEY = 'c24-comparison-form-v1'
export const FORM_STORAGE_VERSION = 1
export const FORM_STORAGE_TTL_MS = 24 * 60 * 60 * 1000

export type StoredFormPayload = {
  version: number
  savedAt: number
  values: FormValues
}

export type FormStorageOptions = {
  now?: () => number
  storage?: Storage
}

export function useFormStorage(options: FormStorageOptions = {}) {
  const now = options.now ?? Date.now
  const storage = options.storage ?? defaultStorage()

  function read(): FormValues | null {
    if (storage === null) {
      return null
    }

    let raw: string | null
    try {
      raw = storage.getItem(FORM_STORAGE_KEY)
    } catch {
      return null
    }

    if (raw === null) {
      return null
    }

    try {
      const parsed: unknown = JSON.parse(raw)
      if (!isStoredPayload(parsed)) {
        return null
      }
      if (parsed.version !== FORM_STORAGE_VERSION) {
        return null
      }
      if (now() - parsed.savedAt >= FORM_STORAGE_TTL_MS) {
        return null
      }
      if (!hasAnyValue(parsed.values)) {
        return null
      }

      return parsed.values
    } catch {
      return null
    }
  }

  function write(values: FormValues): void {
    if (storage === null) {
      return
    }

    const payload: StoredFormPayload = {
      version: FORM_STORAGE_VERSION,
      savedAt: now(),
      values,
    }

    try {
      storage.setItem(FORM_STORAGE_KEY, JSON.stringify(payload))
    } catch {
      return
    }
  }

  function clear(): void {
    if (storage === null) {
      return
    }

    try {
      storage.removeItem(FORM_STORAGE_KEY)
    } catch {
      return
    }
  }

  return { read, write, clear }
}

function defaultStorage(): Storage | null {
  try {
    return globalThis.localStorage
  } catch {
    return null
  }
}

function isStoredPayload(value: unknown): value is StoredFormPayload {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const payload = value as { version?: unknown; savedAt?: unknown; values?: unknown }

  return (
    payload.version === FORM_STORAGE_VERSION
    && typeof payload.savedAt === 'number'
    && Number.isFinite(payload.savedAt)
    && isFormValues(payload.values)
  )
}

function isFormValues(value: unknown): value is FormValues {
  if (typeof value !== 'object' || value === null) {
    return false
  }

  const values = value as Record<string, unknown>

  return (
    typeof values.dateOfBirth === 'string'
    && typeof values.postalCode === 'string'
    && typeof values.carCategory === 'string'
    && typeof values.usage === 'string'
    && typeof values.annualMileage === 'string'
    && (typeof values.garage === 'boolean' || values.garage === null)
    && typeof values.coverage === 'string'
  )
}
