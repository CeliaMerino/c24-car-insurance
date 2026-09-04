export const CAR_CATEGORY_OPTIONS = [
  { value: 'compact', label: 'Compact' },
  { value: 'sedan', label: 'Sedan' },
  { value: 'suv', label: 'SUV' },
  { value: 'van', label: 'Van' },
] as const

export const USAGE_OPTIONS = [
  { value: 'private', label: 'Private' },
  { value: 'commercial', label: 'Commercial' },
] as const

export const MILEAGE_OPTIONS = [
  { value: 'under_5k', label: 'Under 5.000 km' },
  { value: '5k_15k', label: '5.000–15.000 km' },
  { value: '15k_30k', label: '15.000–30.000 km' },
  { value: 'over_30k', label: 'Over 30.000 km' },
] as const

export const GARAGE_OPTIONS = [
  { value: true, label: 'Yes' },
  { value: false, label: 'No' },
] as const

export const COVERAGE_OPTIONS = [
  {
    value: 'third_party',
    title: 'Third party',
    meaning: 'Damage caused to others',
  },
  {
    value: 'third_party_plus',
    title: 'Third party plus',
    meaning: 'Damage caused to others, plus theft, fire and glass',
  },
  {
    value: 'comprehensive',
    title: 'Comprehensive',
    meaning: 'All of the above, plus damage to the customer\'s own vehicle',
  },
] as const

export function coverageTitle(value: string): string {
  const option = COVERAGE_OPTIONS.find((candidate) => candidate.value === value)
  return option === undefined ? value : option.title
}
