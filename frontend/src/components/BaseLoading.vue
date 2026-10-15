<script setup>
defineProps({
  type: {
    type: String,
    default: 'spinner',
    validator: (v) => ['spinner', 'dots', 'skeleton'].includes(v),
  },
  text: {
    type: String,
    default: 'Memuat...',
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
  },
})
</script>

<template>
  <div v-if="type === 'skeleton'" class="w-full space-y-3 p-4 animate-pulse">
    <div class="h-4 bg-base-300 rounded-sm w-3/4"></div>
    <div class="h-4 bg-base-300 rounded-sm w-1/2"></div>
    <div class="h-4 bg-base-300 rounded-sm w-5/6"></div>
  </div>

  <div v-else class="flex flex-col items-center justify-center p-6 space-y-2">
    <span :class="['loading text-primary', `loading-${type}`, `loading-${size}`]"></span>
    <p v-if="text" class="text-xs text-base-content/60 font-medium tracking-wide">{{ text }}</p>
  </div>
</template>
