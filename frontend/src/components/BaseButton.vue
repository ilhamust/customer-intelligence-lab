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
    class="btn font-semibold transition-all duration-150 inline-flex items-center justify-center gap-2 select-none active:scale-[0.98]"
    :class="[
      size === 'xs' ? 'btn-xs rounded-lg text-xs px-2.5 h-7' : '',
      size === 'sm' ? 'btn-sm rounded-xl text-xs px-3.5 h-9' : '',
      size === 'md' ? 'btn-md rounded-xl text-sm px-4 h-10' : '',
      size === 'lg' ? 'btn-lg rounded-xl text-base px-6 h-12' : '',
      variant === 'outline'
        ? 'bg-transparent border border-base-300 text-base-content hover:bg-base-200 hover:border-base-300 hover:text-base-content'
        : variant === 'ghost'
          ? 'bg-transparent border-0 text-base-content/70 hover:bg-base-200 hover:text-base-content'
          : variant === 'accent'
            ? 'btn-accent text-accent-content font-bold shadow-xs hover:brightness-105 border-0'
            : variant === 'primary'
              ? 'btn-primary text-primary-content font-semibold shadow-xs hover:brightness-105 border-0'
              : `btn-${variant} shadow-xs border-0`,
      { 'w-full': block },
      { 'opacity-60 pointer-events-none cursor-not-allowed': disabled || loading }
    ]"
    @click="$emit('click', $event)"
  >
    <span v-if="loading" class="loading loading-spinner" :class="`loading-${size}`"></span>
    <slot name="prefix" />
    <slot />
    <slot name="suffix" />
  </button>
</template>
