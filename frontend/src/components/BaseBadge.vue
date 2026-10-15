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
  pill: {
    type: Boolean,
    default: false,
  },
})
</script>

<template>
  <span
    class="inline-flex items-center justify-center font-semibold leading-none tracking-tight select-none"
    :class="[
      pill ? 'rounded-full' : (size === 'xs' ? 'rounded-md' : size === 'lg' ? 'rounded-xl' : 'rounded-lg'),
      size === 'xs' ? 'text-[10px] px-1.5 py-0.5 gap-1' : '',
      size === 'sm' ? 'text-[11px] px-2 py-1 gap-1' : '',
      size === 'md' ? 'text-xs px-2.5 py-1 gap-1.5' : '',
      size === 'lg' ? 'text-sm px-3 py-1.5 gap-2' : '',
      outline
        ? 'bg-transparent border border-base-300 text-base-content'
        : variant === 'neutral'
          ? 'bg-base-200 text-base-content/80 border border-base-300'
          : variant === 'accent'
            ? 'bg-accent text-accent-content font-bold'
            : variant === 'primary'
              ? 'bg-primary text-primary-content'
              : variant === 'secondary'
                ? 'bg-secondary text-secondary-content'
                : `badge-${variant}`
    ]"
  >
    <slot />
  </span>
</template>
