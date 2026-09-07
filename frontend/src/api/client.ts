import type {
  CampaignDto,
  Comparison,
  ComparisonRequest,
  ComparisonRequestDto,
  ComparisonResponseDto,
  Offer,
  OfferDto,
  PartnerOutcome,
  PartnerOutcomeDto,
  ValidationErrorDto,
  ValidationFieldError,
  ValidationProblemDto,
} from './types'

const DEFAULT_BASE_URL = '/api/v1'

export type ComparisonOk = {
  kind: 'ok'
  comparison: Comparison
}

export type ComparisonValidation = {
  kind: 'validation'
  errors: ValidationFieldError[]
}

export type ComparisonFailure = {
  kind: 'failure'
  status: number | null
}

export type ComparisonResult = ComparisonOk | ComparisonValidation | ComparisonFailure

export function apiBaseUrl(): string {
  const fromEnv = import.meta.env.VITE_API_BASE_URL
  if (typeof fromEnv === 'string' && fromEnv.trim() !== '') {
    return fromEnv.replace(/\/$/, '')
  }

  return DEFAULT_BASE_URL
}

export async function createComparison(request: ComparisonRequest): Promise<ComparisonResult> {
  let response: Response

  try {
    response = await fetch(`${apiBaseUrl()}/comparisons`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(toRequestDto(request)),
    })
  } catch {
    return { kind: 'failure', status: null }
  }

  if (response.status === 422) {
    return parseValidation(response)
  }

  if (!response.ok) {
    return { kind: 'failure', status: response.status }
  }

  try {
    const dto = (await response.json()) as ComparisonResponseDto
    return { kind: 'ok', comparison: fromResponseDto(dto) }
  } catch {
    return { kind: 'failure', status: response.status }
  }
}

export type FrontendEvent = 'form_started' | 'form_restored' | 'results_viewed'

/** Fire-and-forget funnel event. Failures are ignored so the UI never blocks on analytics. */
export async function postEvent(event: FrontendEvent): Promise<void> {
  try {
    await fetch(`${apiBaseUrl()}/events`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ event }),
    })
  } catch {
    return
  }
}

function toRequestDto(request: ComparisonRequest): ComparisonRequestDto {
  return {
    date_of_birth: request.dateOfBirth,
    postal_code: request.postalCode,
    car_category: request.carCategory,
    usage: request.usage,
    annual_mileage: request.annualMileage,
    garage: request.garage,
    coverage: request.coverage,
  }
}

function fromResponseDto(dto: ComparisonResponseDto): Comparison {
  return {
    comparisonId: dto.comparison_id,
    coverage: dto.coverage,
    currency: dto.currency,
    durationMs: dto.duration_ms,
    offers: dto.offers.map(fromOfferDto),
    partners: dto.partners.map(fromPartnerDto),
  }
}

function fromOfferDto(dto: OfferDto): Offer {
  return {
    partner: dto.partner,
    partnerDisplayName: dto.partner_display_name,
    baseAnnualPremiumCents: dto.base_annual_premium_cents,
    finalAnnualPremiumCents: dto.final_annual_premium_cents,
    campaign: dto.campaign === null ? null : fromCampaignDto(dto.campaign),
  }
}

function fromCampaignDto(dto: CampaignDto): { label: string; percentage: number } {
  return {
    label: dto.label,
    percentage: dto.percentage,
  }
}

function fromPartnerDto(dto: PartnerOutcomeDto): PartnerOutcome {
  return {
    partner: dto.partner,
    status: dto.status,
    durationMs: dto.duration_ms,
  }
}

async function parseValidation(response: Response): Promise<ComparisonResult> {
  try {
    const body = (await response.json()) as ValidationProblemDto
    if (!Array.isArray(body.errors)) {
      return { kind: 'failure', status: 422 }
    }

    return {
      kind: 'validation',
      errors: body.errors.map(fromValidationErrorDto),
    }
  } catch {
    return { kind: 'failure', status: 422 }
  }
}

function fromValidationErrorDto(dto: ValidationErrorDto): ValidationFieldError {
  return {
    field: dto.field,
    code: dto.code,
    message: dto.message,
  }
}
