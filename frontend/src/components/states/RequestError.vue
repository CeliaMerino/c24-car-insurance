<script setup lang="ts">
defineProps<{
  message: string
}>()

const emit = defineEmits<{
  retry: []
}>()
</script>

<template>
  <section class="state-panel" role="alert">
    <h2 class="state-panel__title">Something went wrong</h2>
    <p class="state-panel__body">{{ message }}</p>
    <div class="state-panel__actions">
      <button type="button" class="button" @click="emit('retry')">Try again</button>
    </div>
  </section>
</template>
