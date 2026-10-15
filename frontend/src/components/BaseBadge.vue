<script setup>
defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error', 'ghost'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
  },
  outline: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <span
    class="badge font-semibold"
    :class="[
      `badge-${variant}`,
      `badge-${size}`,
      { 'badge-outline': outline }
    ]"
  >
    <slot />
  </span>
</template>
