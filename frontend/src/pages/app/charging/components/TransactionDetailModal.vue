<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { formatDateTime, formatPhone, formatRupiah, formatNumber } from '@/utils/helper'
import BaseButton from '@/components/BaseButton.vue'
import BaseBadge from '@/components/BaseBadge.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  transaction: {
    type: Object,
    default: null,
  },
})

defineEmits(['close'])

const router = useRouter()

const isMember = computed(() => !!props.transaction?.customer_id)

const handleOpenChat = (phone) => {
  if (!phone) return
  router.push({ path: '/app/conversations', query: { search: phone } })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && transaction"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-xl w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-3 border-b border-base-300 pb-3">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <h3 class="font-bold text-base text-base-content font-mono tracking-tight">
                {{ transaction.external_id }}
              </h3>
              <BaseBadge
                :variant="transaction.status === 'completed' ? 'success' : 'neutral'"
                size="xs"
              >
                {{ transaction.status }}
              </BaseBadge>
            </div>
            <p class="text-xs text-base-content/60">
              ID Database: <span class="font-mono">{{ transaction.id }}</span>
            </p>
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

        <!-- Detail Cards Grid -->
        <div class="space-y-4 text-xs">
          <!-- 1. Customer & Vehicle Information -->
          <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/40 space-y-2.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold text-base-content/50 uppercase tracking-wider">
                Profil Pelanggan & Kendaraan
              </span>
              <BaseBadge
                :variant="isMember ? 'primary' : 'neutral'"
                size="xs"
              >
                {{ isMember ? 'Member Terdaftar' : 'Driver Non-Member' }}
              </BaseBadge>
            </div>

            <div class="grid grid-cols-2 gap-2 text-base-content">
              <div>
                <span class="text-[10px] text-base-content/50 block">Nama Driver</span>
                <span class="font-bold text-sm">
                  {{ transaction.customer?.name || 'Driver Tamu / Non-Member' }}
                </span>
                <span v-if="transaction.customer?.customer_code" class="text-[10px] text-primary font-mono block">
                  {{ transaction.customer.customer_code }}
                </span>
              </div>

              <div>
                <span class="text-[10px] text-base-content/50 block">Nomor WhatsApp</span>
                <a
                  v-if="transaction.phone"
                  :href="`https://wa.me/${transaction.phone}`"
                  target="_blank"
                  class="font-mono text-primary font-semibold hover:underline inline-flex items-center gap-1"
                >
                  {{ formatPhone(transaction.phone) }}
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                    <polyline points="15 3 21 3 21 9" />
                    <line x1="10" y1="14" x2="21" y2="3" />
                  </svg>
                </a>
                <span v-else class="text-base-content/40">-</span>
              </div>

              <div>
                <span class="text-[10px] text-base-content/50 block">Plat Nomor Kendaraan</span>
                <span class="font-mono font-bold uppercase">
                  {{ transaction.plate_number || '-' }}
                </span>
              </div>

              <div>
                <span class="text-[10px] text-base-content/50 block">Model Kendaraan</span>
                <span>{{ transaction.customer?.vehicle || '-' }}</span>
              </div>
            </div>
          </div>

          <!-- 2. Station & Charger Information -->
          <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/40 space-y-2.5">
            <span class="text-[11px] font-extrabold text-base-content/50 uppercase tracking-wider block">
              Lokasi Stasiun & Perangkat SPKLU
            </span>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <span class="text-[10px] text-base-content/50 block">Nama Stasiun</span>
                <span class="font-bold">{{ transaction.station_name }}</span>
                <span class="text-[10px] text-base-content/60 font-mono block">
                  ({{ transaction.station_code }})
                </span>
              </div>

              <div>
                <span class="text-[10px] text-base-content/50 block">Charger & Tipe</span>
                <span class="font-mono font-bold">{{ transaction.charger_code }}</span>
                <span class="text-[10px] text-base-content/60 block">
                  {{ transaction.charger_type || 'Standard Fast Charging' }}
                </span>
              </div>
            </div>
          </div>

          <!-- 3. Energy, Cost & Time Metrics -->
          <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/40 space-y-2.5">
            <span class="text-[11px] font-extrabold text-base-content/50 uppercase tracking-wider block">
              Parameter Pengisian & Finansial
            </span>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
              <div class="p-2.5 rounded-lg bg-base-100 border border-base-300 space-y-0.5">
                <span class="text-[10px] text-base-content/50 block">Konsumsi Daya</span>
                <span class="text-base font-black text-primary font-mono">
                  {{ formatNumber(transaction.kwh) }}
                </span>
                <span class="text-[10px] text-base-content/60 block">kWh</span>
              </div>

              <div class="p-2.5 rounded-lg bg-base-100 border border-base-300 space-y-0.5">
                <span class="text-[10px] text-base-content/50 block">Total Biaya</span>
                <span class="text-base font-black text-emerald-600 font-mono">
                  {{ formatRupiah(transaction.amount) }}
                </span>
                <span class="text-[10px] text-base-content/60 block">Rupiah</span>
              </div>

              <div class="p-2.5 rounded-lg bg-base-100 border border-base-300 space-y-0.5">
                <span class="text-[10px] text-base-content/50 block">Durasi Pengisian</span>
                <span class="text-base font-black text-base-content font-mono">
                  {{ transaction.duration_minutes || '-' }}
                </span>
                <span class="text-[10px] text-base-content/60 block">Menit</span>
              </div>

              <div class="p-2.5 rounded-lg bg-base-100 border border-base-300 space-y-0.5">
                <span class="text-[10px] text-base-content/50 block">Poin Diperoleh</span>
                <span class="text-base font-black text-amber-500 font-mono">
                  +{{ transaction.points_awarded || 0 }}
                </span>
                <span class="text-[10px] text-base-content/60 block">Poin</span>
              </div>
            </div>

            <!-- Timestamps -->
            <div class="pt-2 border-t border-base-300/80 flex flex-col sm:flex-row justify-between text-[11px] text-base-content/60 gap-1">
              <span>Waktu Selesai: <strong>{{ formatDateTime(transaction.finished_at) }}</strong></span>
              <span v-if="transaction.started_at">Waktu Mulai: {{ formatDateTime(transaction.started_at) }}</span>
            </div>
          </div>

          <!-- 4. Loyalty Ledger Record (If Member) -->
          <div
            v-if="transaction.points_ledger"
            class="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5 space-y-1.5"
          >
            <div class="flex items-center justify-between text-amber-700 dark:text-amber-400">
              <span class="text-[10px] font-bold uppercase tracking-wider flex items-center gap-1">
                ⚡ Mutasi Buku Besar Poin Loyalitas
              </span>
              <span class="font-mono text-xs font-bold">+{{ transaction.points_ledger.points }} Poin</span>
            </div>
            <p class="text-[11px] text-base-content/80 italic">
              "{{ transaction.points_ledger.description }}"
            </p>
            <span class="text-[10px] text-base-content/50 block">
              Saldo akun setelah pengisian: <strong>{{ transaction.points_ledger.balance_after }} Poin</strong>
            </span>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="flex items-center justify-between pt-3 border-t border-base-300">
          <div>
            <button
              v-if="transaction.phone"
              type="button"
              @click="handleOpenChat(transaction.phone)"
              class="btn btn-ghost btn-xs text-primary font-semibold gap-1"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
              </svg>
              Buka Chat Pelanggan
            </button>
          </div>

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

