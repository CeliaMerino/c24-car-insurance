<script setup lang="ts">
const emit = defineEmits<{
  dismiss: []
  clear: []
}>()
</script>

<template>
  <div class="restore-notice" role="status">
    <p class="restore-notice__text">Your previous details were restored.</p>
    <div class="restore-notice__actions">
      <button type="button" class="button button--ghost" @click="emit('dismiss')">Dismiss</button>
      <button type="button" class="button button--ghost" @click="emit('clear')">
        Clear saved details
      </button>
    </div>
  </div>
</template>
