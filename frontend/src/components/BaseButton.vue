<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'accent', 'neutral', 'ghost', 'outline'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
  type: {
    type: String,
    default: 'button',
  },
})

defineEmits(['click'])
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="btn font-medium transition-colors"
    :class="[
      variant === 'outline' ? 'btn-outline border-base-300' : `btn-${variant}`,
      `btn-${size}`,
      { 'w-full': block }
    ]"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="loading loading-spinner" :class="`loading-${size}`"></span>
    <slot name="prefix" />
    <slot />
    <slot name="suffix" />
  </button>
</template>
