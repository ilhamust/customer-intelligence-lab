<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  trend: {
    type: String,
    default: '',
  },
  trendType: {
    type: String,
    default: 'neutral',
    validator: (v) => ['up', 'down', 'neutral'].includes(v),
  },
  subtext: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="bg-base-100 rounded-2xl border border-base-300 p-5 shadow-xs flex items-center justify-between select-none">
    <div class="space-y-1">
      <p class="text-[11px] font-bold text-base-content/60 uppercase tracking-wider">{{ label }}</p>
      <h3 class="text-2xl font-black text-base-content tracking-tight">{{ value }}</h3>
      
      <div v-if="trend || subtext" class="flex items-center gap-1.5 text-xs pt-0.5">
        <span
          v-if="trend"
          class="font-semibold flex items-center gap-0.5"
          :class="[
            trendType === 'up'
              ? 'text-success'
              : trendType === 'down'
                ? 'text-error'
                : 'text-base-content/60'
          ]"
        >
          <!-- Trend Arrow Icons -->
          <svg v-if="trendType === 'up'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15" />
          </svg>
          <svg v-else-if="trendType === 'down'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 12 15 18 9" />
          </svg>
          {{ trend }}
        </span>
        <span v-if="subtext" class="text-base-content/50">{{ subtext }}</span>
      </div>
    </div>

    <!-- Icon Squircle Slot -->
    <div v-if="$slots.icon" class="w-12 h-12 rounded-2xl bg-base-200 border border-base-300 flex items-center justify-center text-primary shrink-0 shadow-xs">
      <slot name="icon" />
    </div>
  </div>
</template>
