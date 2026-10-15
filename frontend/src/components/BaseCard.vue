<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  bordered: {
    type: Boolean,
    default: true,
  },
  padding: {
    type: String,
    default: 'p-5',
  },
})
</script>

<template>
  <div
    class="bg-base-100 rounded-2xl shadow-xs flex flex-col overflow-hidden"
    :class="[
      { 'border border-base-300': bordered }
    ]"
  >
    <div
      v-if="title || $slots.header || $slots.actions"
      class="px-5 py-4 flex items-center justify-between border-b border-base-300 shrink-0"
    >
      <div>
        <slot name="header">
          <h3 class="font-bold text-sm sm:text-base text-base-content tracking-tight">{{ title }}</h3>
          <p v-if="subtitle" class="text-xs text-base-content/60 mt-0.5">{{ subtitle }}</p>
        </slot>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </div>

    <div :class="['flex-1', padding]">
      <slot />
    </div>

    <div
      v-if="$slots.footer"
      class="px-5 py-3.5 bg-base-100 border-t border-base-300 shrink-0 flex items-center justify-between"
    >
      <slot name="footer" />
    </div>
  </div>
</template>
