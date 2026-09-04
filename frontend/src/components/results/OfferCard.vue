<script setup lang="ts">
import type { Offer } from '../../api/types'

defineProps<{
  offer: Offer
}>()

function formatCents(cents: number): string {
  const negative = cents < 0
  const absolute = Math.abs(cents)
  const euros = Math.floor(absolute / 100)
  const remainder = String(absolute % 100).padStart(2, '0')
  const grouped = String(euros).replace(/\B(?=(\d{3})+(?!\d))/g, ',')

  return `${negative ? '-' : ''}€${grouped}.${remainder}`
}
</script>

<template>
  <article class="offer-card">
    <h3 class="offer-card__partner">{{ offer.partnerDisplayName }}</h3>
    <p v-if="offer.campaign !== null" class="offer-card__campaign">{{ offer.campaign.label }}</p>
    <p v-if="offer.campaign !== null" class="offer-card__base">
      <s>{{ formatCents(offer.baseAnnualPremiumCents) }}</s>
    </p>
    <p class="offer-card__price">
      {{ formatCents(offer.finalAnnualPremiumCents) }}
      <span class="offer-card__period">/ year</span>
    </p>
  </article>
</template>
