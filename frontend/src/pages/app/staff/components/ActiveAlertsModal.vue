<script setup>
import { useRouter } from 'vue-router'
import { useStaffStore } from '@/stores/app/staff.store'
import { notify } from '@/plugins'
import BaseButton from '@/components/BaseButton.vue'
import BaseBadge from '@/components/BaseBadge.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close'])

const router = useRouter()
const p$staff = useStaffStore()

const handleOpenChat = (conversationId) => {
  if (!conversationId) return
  emit('close')
  router.push({ path: '/app/conversations', query: { id: conversationId } })
}

const handleAcknowledge = async (alertId) => {
  try {
    await p$staff.a$acknowledgeAlert(alertId, 'Admin On-Duty')
    notify.success('Alert berhasil di-acknowledge! Tim lapangan telah tercatat menangani insiden ini.')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal meng-acknowledge alert')
  }
}

const getCategoryLabel = (category) => {
  switch (category) {
    case 'safety_hazard':
      return 'Bahaya Keselamatan / Api / Korslet'
    case 'trapped_cable':
      return 'Kabel / Konektor Terkunci'
    case 'power_outage':
      return 'Listrik / Charger Mati Mendadak'
    case 'hardware_failure':
      return 'Kerusakan Fisik Perangkat'
    default:
      return 'Insiden Darurat 24/7'
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-error/30 shadow-2xl max-w-2xl w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[85vh] flex flex-col">
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-3 border-b border-base-300 pb-3 shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-error/10 border border-error/30 text-error flex items-center justify-center shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 animate-bounce" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
            </div>
            <div>
              <h3 class="font-bold text-base text-base-content flex items-center gap-2">
                Monitor Insiden Darurat 24/7
                <span class="badge badge-error badge-sm font-bold text-white">
                  {{ p$staff.g$activeAlertsCount }} Aktif
                </span>
              </h3>
              <p class="text-xs text-base-content/60">
                Peringatan instan dari pengguna di stasiun SPKLU yang membutuhkan respon segera.
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content shrink-0"
            title="Tutup dialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </div>

        <!-- Alert Items List -->
        <div class="flex-1 overflow-y-auto space-y-3 pr-1">
          <div
            v-if="p$staff.g$activeAlerts.length === 0"
            class="text-center py-12 space-y-3"
          >
            <div class="w-12 h-12 rounded-full bg-success/10 text-success mx-auto flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                <polyline points="22 4 12 14.01 9 11.01" />
              </svg>
            </div>
            <p class="text-sm font-bold text-base-content">Semua Aman! Tidak Ada Insiden Aktif</p>
            <p class="text-xs text-base-content/60">Semua stasiun SPKLU dan sistem pengisian beroperasi dengan normal.</p>
          </div>

          <div
            v-for="alert in p$staff.g$activeAlerts"
            :key="alert.id"
            class="p-4 rounded-xl border border-error/30 bg-error/5 space-y-3 transition-all hover:border-error/50"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-mono font-bold text-xs bg-error text-white px-2 py-0.5 rounded-md">
                    {{ alert.alert_code }}
                  </span>
                  <span class="text-xs font-semibold text-error uppercase tracking-wider">
                    {{ getCategoryLabel(alert.incident_category) }}
                  </span>
                  <BaseBadge variant="error" size="xs">
                    {{ alert.status }}
                  </BaseBadge>
                </div>
                <p class="text-xs font-medium text-base-content/80">
                  Customer: <strong class="text-base-content">{{ alert.customer?.name || alert.conversation?.name || 'Driver' }}</strong>
                  ({{ alert.customer?.phone || alert.conversation?.phone || '-' }})
                  <span v-if="alert.customer?.vehicle"> • {{ alert.customer.vehicle }}</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <span class="text-[10px] text-base-content/50 font-mono">
                  Tiket: {{ alert.ticket?.ticket_code || '-' }}
                </span>
              </div>
            </div>

            <!-- Raw Message Quote -->
            <div class="p-2.5 rounded-lg bg-base-100 border border-base-300 text-xs text-base-content/90 font-mono italic">
              "{{ alert.raw_message }}"
            </div>

            <!-- Dispatched Staff Info & Actions -->
            <div class="flex items-center justify-between gap-3 pt-1 text-xs">
              <div class="text-[11px] text-base-content/60 flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                </svg>
                <span>Broadcast dikirim ke {{ (alert.dispatched_recipients || []).length }} nomor teknisi</span>
              </div>

              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="handleOpenChat(alert.conversation_id)"
                  class="btn btn-ghost btn-xs text-primary font-semibold"
                >
                  Buka Obrolan
                </button>

                <BaseButton
                  v-if="alert.status !== 'acknowledged'"
                  variant="primary"
                  size="xs"
                  @click="handleAcknowledge(alert.id)"
                >
                  Acknowledge (Sedang Ditangani)
                </BaseButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-end pt-3 border-t border-base-300 shrink-0">
          <BaseButton
            type="button"
            variant="outline"
            size="sm"
            @click="$emit('close')"
          >
            Tutup
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>

