export type CarCategory = 'compact' | 'sedan' | 'suv' | 'van'
export type Usage = 'private' | 'commercial'
export type AnnualMileage = 'under_5k' | '5k_15k' | '15k_30k' | 'over_30k'
export type Coverage = 'third_party' | 'third_party_plus' | 'comprehensive'
export type PartnerStatus = 'ok' | 'timeout' | 'error' | 'skipped'
export type Currency = 'EUR'

export type ValidationCode =
  | 'required'
  | 'format'
  | 'min_age'
  | 'future_date'
  | 'unknown_value'
  | 'unknown_field'

export type ComparisonRequestDto = {
  date_of_birth: string
  postal_code: string
  car_category: CarCategory
  usage: Usage
  annual_mileage: AnnualMileage
  garage: boolean
  coverage: Coverage
}

export type CampaignDto = {
  label: string
  percentage: number
}

export type OfferDto = {
  partner: string
  partner_display_name: string
  base_annual_premium_cents: number
  final_annual_premium_cents: number
  campaign: CampaignDto | null
}

export type PartnerOutcomeDto = {
  partner: string
  status: PartnerStatus
  duration_ms: number
}

export type ComparisonResponseDto = {
  comparison_id: string
  coverage: Coverage
  currency: Currency
  duration_ms: number
  offers: OfferDto[]
  partners: PartnerOutcomeDto[]
}

export type ValidationErrorDto = {
  field: string
  code: ValidationCode
  message: string
}

export type ValidationProblemDto = {
  type: string
  title: string
  status: 422
  errors: ValidationErrorDto[]
}

export type ComparisonRequest = {
  dateOfBirth: string
  postalCode: string
  carCategory: CarCategory
  usage: Usage
  annualMileage: AnnualMileage
  garage: boolean
  coverage: Coverage
}

export type Campaign = {
  label: string
  percentage: number
}

export type Offer = {
  partner: string
  partnerDisplayName: string
  baseAnnualPremiumCents: number
  finalAnnualPremiumCents: number
  campaign: Campaign | null
}

export type PartnerOutcome = {
  partner: string
  status: PartnerStatus
  durationMs: number
}

export type Comparison = {
  comparisonId: string
  coverage: Coverage
  currency: Currency
  durationMs: number
  offers: Offer[]
  partners: PartnerOutcome[]
}

export type ValidationFieldError = {
  field: string
  code: ValidationCode
  message: string
}
