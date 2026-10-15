<script setup>
defineProps({
  title: {
    type: String,
    default: 'No data found',
  },
  description: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center p-8 space-y-3.5 select-none">
    <div class="w-14 h-14 rounded-2xl bg-base-200 border border-base-300 flex items-center justify-center text-base-content/40 shadow-xs">
      <slot name="icon">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          <line x1="9" y1="10" x2="15" y2="10" />
        </svg>
      </slot>
    </div>

    <div class="space-y-1 max-w-sm">
      <h4 class="font-bold text-sm text-base-content tracking-tight">{{ title }}</h4>
      <p v-if="description" class="text-xs text-base-content/60 leading-relaxed max-w-xs">{{ description }}</p>
    </div>

    <div v-if="$slots.action" class="pt-2">
      <slot name="action" />
    </div>
  </div>
</template>
