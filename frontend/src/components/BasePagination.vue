<script setup>
import { computed } from 'vue'

const props = defineProps({
  meta: {
    type: Object,
    required: true,
    // Expected: { current_page, last_page, total, per_page, from, to }
  },
})

const emit = defineEmits(['change'])

const currentPage = computed(() => Number(props.meta?.current_page || 1))
const lastPage = computed(() => Number(props.meta?.last_page || 1))
const total = computed(() => Number(props.meta?.total || 0))
const from = computed(() => props.meta?.from || ((currentPage.value - 1) * (props.meta?.per_page || 10) + 1))
const to = computed(() => props.meta?.to || Math.min(currentPage.value * (props.meta?.per_page || 10), total.value))

const pages = computed(() => {
  const current = currentPage.value
  const last = lastPage.value
  const delta = 1
  const range = []
  
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i)
  }

  if (current - delta > 2) range.unshift('...')
  if (current + delta < last - 1) range.push('...')

  range.unshift(1)
  if (last > 1) range.push(last)

  return range
})

const goToPage = (page) => {
  if (page === '...' || page === currentPage.value || page < 1 || page > lastPage.value) return
  emit('change', page)
}
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-2 py-3 select-none">
    <!-- Meta Summary -->
    <div class="text-xs text-base-content/60 font-medium">
      <span v-if="total > 0">
        Menampilkan <strong class="text-base-content">{{ from }}</strong> - <strong class="text-base-content">{{ to }}</strong> dari <strong class="text-base-content">{{ total }}</strong> data
      </span>
      <span v-else>Tidak ada data</span>
    </div>

    <!-- Page Buttons -->
    <div class="flex items-center gap-1">
      <!-- Prev Button -->
      <button
        type="button"
        @click="goToPage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="h-8 px-2.5 rounded-xl border border-base-300 text-xs font-semibold text-base-content hover:bg-base-200 disabled:opacity-40 disabled:pointer-events-none transition-colors"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6" />
        </svg>
      </button>

      <!-- Page Numbers -->
      <button
        v-for="(page, idx) in pages"
        :key="idx"
        type="button"
        @click="goToPage(page)"
        :disabled="page === '...'"
        class="h-8 min-w-[32px] px-2 rounded-xl text-xs font-semibold transition-colors flex items-center justify-center"
        :class="[
          page === currentPage
            ? 'bg-primary text-primary-content shadow-xs'
            : page === '...'
              ? 'cursor-default text-base-content/40'
              : 'border border-base-300 text-base-content hover:bg-base-200'
        ]"
      >
        {{ page }}
      </button>

      <!-- Next Button -->
      <button
        type="button"
        @click="goToPage(currentPage + 1)"
        :disabled="currentPage >= lastPage"
        class="h-8 px-2.5 rounded-xl border border-base-300 text-xs font-semibold text-base-content hover:bg-base-200 disabled:opacity-40 disabled:pointer-events-none transition-colors"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>
  </div>
</template>
