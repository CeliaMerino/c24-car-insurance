<script setup lang="ts">
import type { Coverage, Offer } from '../../api/types'
import { coverageTitle } from '../form/options'
import OfferCard from './OfferCard.vue'

defineProps<{
  coverage: Coverage
  offers: Offer[]
}>()
</script>

<template>
  <section class="offer-list">
    <h2 class="offer-list__heading">
      Offers for {{ coverageTitle(coverage) }}
    </h2>
    <ul class="offer-list__items">
      <li v-for="offer in offers" :key="offer.partner">
        <OfferCard :offer="offer" />
      </li>
    </ul>
  </section>
</template>
