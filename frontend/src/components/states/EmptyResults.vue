<script setup lang="ts">
const emit = defineEmits<{
  retry: []
  changeDetails: []
}>()
</script>

<template>
  <section class="state-panel" role="status">
    <h2 class="state-panel__title">No offers could be retrieved</h2>
    <p class="state-panel__body">
      None of the insurers returned a price for this comparison. You can try again.
    </p>
    <div class="state-panel__actions">
      <button type="button" class="button" @click="emit('retry')">Try again</button>
      <button type="button" class="button button--secondary" @click="emit('changeDetails')">
        Change details
      </button>
    </div>
  </section>
</template>
