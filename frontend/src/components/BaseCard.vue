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
    class="card bg-base-100 shadow-xs"
    :class="[
      { 'border border-base-300': bordered }
    ]"
  >
    <div v-if="title || $slots.header || $slots.actions" class="px-5 pt-5 pb-3 flex items-center justify-between border-b border-base-300/60">
      <div>
        <slot name="header">
          <h3 class="font-bold text-base text-base-content">{{ title }}</h3>
          <p v-if="subtitle" class="text-xs text-base-content/60 mt-0.5">{{ subtitle }}</p>
        </slot>
      </div>
      <div v-if="$slots.actions" class="card-actions">
        <slot name="actions" />
      </div>
    </div>

    <div :class="['card-body', padding]">
      <slot />
    </div>
  </div>
</template>
