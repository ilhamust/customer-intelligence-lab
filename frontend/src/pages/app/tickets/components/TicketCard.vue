<script setup>
import { formatPhone, formatRelativeTime } from '@/utils/helper'

defineProps({
  ticket: {
    type: Object,
    required: true,
  },
  actionLoading: {
    type: Boolean,
    default: false,
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
  <div class="bg-base-100 rounded-2xl border border-base-300 p-5 shadow-xs hover:border-base-content/20 transition-all flex flex-col md:flex-row md:items-center justify-between gap-4">
    <!-- Kolom Kiri: Antrean & Profil Pelanggan -->
    <div class="flex items-start gap-4 min-w-0">
      <!-- Nomor Antrean / Badge Status -->
      <div class="shrink-0 flex flex-col items-center justify-center text-center">
        <template v-if="ticket.status === 'queued'">
          <div class="w-13 h-13 rounded-2xl bg-amber-500/10 border-2 border-amber-500/40 text-amber-600 dark:text-amber-400 flex flex-col items-center justify-center font-black">
            <span class="text-[9px] uppercase tracking-tighter leading-none text-amber-500/80">Antrean</span>
            <span class="text-lg leading-tight">#{{ ticket.queue_position || 1 }}</span>
          </div>
        </template>

        <template v-else-if="ticket.status === 'in_progress'">
          <div class="w-13 h-13 rounded-2xl bg-blue-500/10 border-2 border-blue-500/40 text-blue-600 dark:text-blue-400 flex flex-col items-center justify-center font-black">
            <span class="text-[9px] uppercase tracking-tighter leading-none text-blue-500/80">Status</span>
            <span class="text-xs leading-tight">LIVE</span>
          </div>
        </template>

        <template v-else>
          <div class="w-13 h-13 rounded-2xl bg-emerald-500/10 border-2 border-emerald-500/40 text-emerald-600 dark:text-emerald-400 flex flex-col items-center justify-center font-black">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>
        </template>
      </div>

      <!-- Detail Informasi Pelanggan & Masalah -->
      <div class="space-y-1.5 min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="font-mono text-xs font-bold text-base-content/80">
            {{ ticket.ticket_code }}
          </span>
          <BaseBadge :variant="getCategoryBadgeVariant(ticket.category)" pill size="xs">
            {{ ticket.category_label }}
          </BaseBadge>
          <span class="text-[11px] text-base-content/40">•</span>
          <span class="text-[11px] text-base-content/50">
            {{ formatRelativeTime(ticket.created_at) }}
          </span>
        </div>

        <!-- Nama & Kontak -->
        <div class="flex flex-wrap items-baseline gap-2">
          <h3 class="text-sm font-bold text-base-content">
            {{ ticket.customer_name }}
          </h3>
          <span class="text-xs text-base-content/60 font-mono">
            {{ formatPhone(ticket.customer_phone) }}
          </span>
          <span v-if="ticket.vehicle && ticket.vehicle !== '-'" class="text-[11px] px-2 py-0.5 rounded-md bg-base-200 text-base-content/70">
            {{ ticket.vehicle }}
          </span>
        </div>

        <!-- Ringkasan Kendala -->
        <p v-if="ticket.metadata?.issue_summary" class="text-xs text-base-content/70 italic line-clamp-2">
          "{{ ticket.metadata.issue_summary }}"
        </p>

        <!-- Catatan Solusi Jika Resolved -->
        <div v-if="ticket.status === 'resolved' && ticket.resolution_notes" class="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-2.5 py-1 rounded-lg w-fit">
          <span class="font-bold">Solusi:</span> {{ ticket.resolution_notes }}
        </div>
      </div>
    </div>

    <!-- Kolom Kanan: Tombol Aksi Operasional -->
    <div class="flex items-center gap-2.5 shrink-0 self-end md:self-center">
      <!-- Buka Obrolan -->
      <BaseButton
        variant="outline"
        size="sm"
        @click="$emit('open-chat', ticket.conversation_id)"
        title="Buka obrolan percakapan WhatsApp"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5 text-base-content/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
        </svg>
        Obrolan
      </BaseButton>

      <!-- Aksi untuk Queued: Layani Tiket -->
      <template v-if="ticket.status === 'queued'">
        <BaseButton
          variant="primary"
          size="sm"
          :disabled="actionLoading"
          @click="$emit('serve', ticket)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <polyline points="16 11 18 13 22 9" />
          </svg>
          Layani Tiket
        </BaseButton>
      </template>

      <!-- Aksi untuk In Progress: Selesaikan Tiket -->
      <template v-else-if="ticket.status === 'in_progress'">
        <BaseButton
          variant="accent"
          size="sm"
          :disabled="actionLoading"
          @click="$emit('resolve', ticket)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12" />
          </svg>
          Selesaikan
        </BaseButton>
      </template>

      <!-- Status Resolved: Selesai -->
      <template v-else>
        <BaseBadge variant="success" pill size="sm">
          Selesai ({{ formatRelativeTime(ticket.resolved_at) }})
        </BaseBadge>
      </template>
    </div>
  </div>
</template>
