<script setup lang="ts">
defineProps<{
  count?: number
}>()
</script>

<template>
  <section class="offer-list" aria-busy="true" aria-label="Loading offers">
    <h2 class="offer-list__heading">Finding offers</h2>
    <ul class="offer-list__items">
      <li v-for="index in count ?? 4" :key="index">
        <div class="offer-card offer-card--skeleton">
          <div class="skeleton skeleton--name"></div>
          <div class="skeleton skeleton--price"></div>
        </div>
      </li>
    </ul>
  </section>
</template>
