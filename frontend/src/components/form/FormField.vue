<script setup lang="ts">
defineProps<{
  id: string
  label: string
  error: string
}>()
</script>

<template>
  <div class="field">
    <label class="field__label" :for="id">{{ label }}</label>
    <slot />
    <p
      v-if="error !== ''"
      :id="`${id}-error`"
      class="field__error"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>
