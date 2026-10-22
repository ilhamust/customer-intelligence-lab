<script setup>
import { formatPhone } from '@/utils/helper'

defineProps({
  isBusy: {
    type: Boolean,
    default: false,
  },
  activeTicket: {
    type: Object,
    default: null,
  },
  queuedTickets: {
    type: Array,
    default: () => [],
  },
})

defineEmits(['open-chat', 'serve', 'resolve'])

const getCategoryBadgeVariant = (category) => {
  switch (category) {
    case 'points_mismatch':
      return 'warning'
    case 'abnormal_transaction':
      return 'error'
    case 'service_complaint':
      return 'secondary'
    default:
      return 'neutral'
  }
}
</script>

<template>
  <!-- Banner Sedang Melayani Tiket Aktif -->
  <div
    v-if="isBusy && activeTicket"
    class="rounded-2xl border p-5 shadow-xs transition-all duration-300 bg-gradient-to-r from-blue-500/10 via-indigo-500/10 to-transparent border-blue-500/30"
  >
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-start gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-blue-500/15 border border-blue-500/30 flex items-center justify-center shrink-0 text-blue-500">
          <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
          </span>
        </div>

        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-black uppercase tracking-wider text-blue-600 dark:text-blue-400">
              Sedang Melayani Tiket Aktif
            </span>
            <BaseBadge variant="info" pill size="xs">
              {{ activeTicket.ticket_code }}
            </BaseBadge>
            <BaseBadge :variant="getCategoryBadgeVariant(activeTicket.category)" pill size="xs">
              {{ activeTicket.category_label }}
            </BaseBadge>
          </div>

          <p class="text-sm font-bold text-base-content">
            {{ activeTicket.customer_name }}
            <span class="font-normal text-xs text-base-content/60 ml-1">
              ({{ formatPhone(activeTicket.customer_phone) }})
            </span>
          </p>

          <p v-if="activeTicket.metadata?.issue_summary" class="text-xs text-base-content/70 italic line-clamp-1">
            "{{ activeTicket.metadata.issue_summary }}"
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 self-end md:self-center shrink-0">
        <BaseButton
          variant="outline"
          size="sm"
          @click="$emit('open-chat', activeTicket.conversation_id)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          Buka Obrolan
        </BaseButton>

        <BaseButton
          variant="primary"
          size="sm"
          @click="$emit('resolve', activeTicket)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          Selesaikan Tiket
        </BaseButton>
      </div>
    </div>
  </div>

  <!-- Banner CS Standby (Idle) -->
  <div
    v-else
    class="rounded-2xl border p-5 shadow-xs transition-all duration-300 bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-transparent border-emerald-500/30"
  >
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center shrink-0 text-emerald-500">
          <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>

        <div class="space-y-0.5">
          <div class="flex items-center gap-2">
            <span class="text-xs font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
              Customer Service Standby (Idle)
            </span>
            <BaseBadge variant="success" pill size="xs">Siap Melayani</BaseBadge>
          </div>
          <p class="text-xs text-base-content/70">
            Tidak ada tiket yang sedang ditangani. Silakan layani antrean pelanggan berikutnya di bawah.
          </p>
        </div>
      </div>

      <div v-if="queuedTickets.length > 0" class="self-end sm:self-center shrink-0">
        <BaseButton
          variant="primary"
          size="sm"
          @click="$emit('serve', queuedTickets[0])"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <polyline points="16 11 18 13 22 9" />
          </svg>
          Layani Antrean Pertama (#1)
        </BaseButton>
      </div>
    </div>
  </div>
</template>
