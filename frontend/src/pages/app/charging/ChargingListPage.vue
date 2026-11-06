<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useChargingStore } from '@/stores/app/charging.store'
import { formatDateTime, formatPhone, formatRupiah, formatNumber } from '@/utils/helper'
import { notify } from '@/plugins'
import TransactionDetailModal from './components/TransactionDetailModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BasePagination from '@/components/BasePagination.vue'
import BaseStat from '@/components/BaseStat.vue'

const p$charging = useChargingStore()

// State Lokal
const searchInput = ref('')
const selectedStatus = ref('all')
const showDetailModal = ref(false)
const selectedTransaction = ref(null)

const statusOptions = [
  { value: 'all', label: 'Semua Status' },
  { value: 'completed', label: 'Selesai (Completed)' },
  { value: 'ongoing', label: 'Berlangsung (Ongoing)' },
  { value: 'failed', label: 'Gagal (Failed)' },
]

onMounted(async () => {
  try {
    await p$charging.a$fetchTransactions()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memuat transaksi charging')
  }

  // Subscribe ke WebSocket Reverb channel 'charging'
  p$charging.a$initWebSocket()
})

onUnmounted(() => {
  p$charging.a$stopWebSocket()
})

const handleRefresh = async () => {
  try {
    await p$charging.a$fetchTransactions()
    notify.success('Data transaksi charging berhasil diperbarui')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memperbarui data transaksi')
  }
}

const handleSearch = (e) => {
  searchInput.value = e.target.value
  p$charging.a$setFilter('search', e.target.value)
}

const handleStatusChange = (e) => {
  selectedStatus.value = e.target.value
  p$charging.a$setFilter('status', e.target.value)
}

const handlePageChange = (page) => {
  p$charging.a$setPage(page)
}

const handleOpenDetail = (transaction) => {
  selectedTransaction.value = transaction
  showDetailModal.value = true
}
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden bg-base-200">
    <!-- Top Header Bar -->
    <header class="bg-base-100 border-b border-base-300 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-lg font-black text-base-content tracking-tight">
            Transaksi Pengisian Daya (EV Charging)
          </h1>
          <BaseBadge variant="primary" size="xs">Sprint 1 Data</BaseBadge>
        </div>
        <p class="text-xs text-base-content/60 mt-0.5">
          Monitoring aktivitas pengisian daya listrik SPKLU, konsumsi daya kWh, omset, dan kalkulasi poin loyalitas otomatis.
        </p>
      </div>

      <div class="flex items-center gap-2.5">
        <!-- Refresh Button -->
        <BaseButton
          variant="outline"
          size="sm"
          :loading="p$charging.g$loading"
          @click="handleRefresh"
          title="Segarkan data transaksi"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
            <path d="M16 21h5v-5" />
          </svg>
        </BaseButton>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-6 space-y-5">
      <!-- KPI Metrics Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- 1. Total Transaksi -->
        <BaseStat
          label="Total Transaksi"
          :value="p$charging.g$summary.total_transactions"
          :subtext="`${p$charging.g$summary.member_transactions} Member • ${p$charging.g$summary.non_member_transactions} Non-Member`"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2v4a2 2 0 0 0 2 2h4" />
              <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z" />
              <path d="M12 18v-6" />
              <path d="m9 15 3 3 3-3" />
            </svg>
          </template>
        </BaseStat>

        <!-- 2. Total Konsumsi Daya (kWh) -->
        <BaseStat
          label="Konsumsi Energi (kWh)"
          :value="`${formatNumber(p$charging.g$summary.total_kwh)}`"
          subtext="Total energi tersalurkan"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
            </svg>
          </template>
        </BaseStat>

        <!-- 3. Total Pendapatan / Omset (Rp) -->
        <BaseStat
          label="Total Pendapatan"
          :value="formatRupiah(p$charging.g$summary.total_revenue)"
          subtext="Nilai transaksi terbayar"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="2" x2="12" y2="22" />
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </svg>
          </template>
        </BaseStat>

        <!-- 4. Poin Loyalitas Diberikan -->
        <BaseStat
          label="Poin Loyalitas"
          :value="`+${formatNumber(p$charging.g$summary.total_points_awarded, 0)}`"
          subtext="Reward poin anggota aktif"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
            </svg>
          </template>
        </BaseStat>
      </div>

      <!-- Filters & Search Bar Card -->
      <div class="bg-base-100 rounded-2xl border border-base-300 p-4 shadow-xs space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <!-- Search Input -->
          <div class="relative flex-1 max-w-md">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            <input
              type="text"
              :value="searchInput"
              @input="handleSearch"
              placeholder="Cari ID transaksi, nama driver, nomor WA, plat nomor, stasiun..."
              class="input input-sm w-full pl-10 rounded-xl bg-base-200/60 border-base-300 text-xs focus:outline-primary"
            />
          </div>

          <!-- Quick Filters Group -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Status Selector -->
            <select
              :value="selectedStatus"
              @change="handleStatusChange"
              class="select select-bordered select-sm rounded-xl text-xs bg-base-100 focus:outline-primary"
            >
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Transactions Table Card -->
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="table table-sm w-full">
            <thead class="bg-base-200/60 border-b border-base-300 text-base-content/60 text-[11px] uppercase tracking-wider">
              <tr>
                <th class="py-3 px-4">Waktu Selesai</th>
                <th class="py-3 px-4">ID Transaksi & Charger</th>
                <th class="py-3 px-4">Driver / Kendaraan</th>
                <th class="py-3 px-4">Lokasi Stasiun</th>
                <th class="py-3 px-4 text-right">Daya (kWh)</th>
                <th class="py-3 px-4 text-right">Biaya (Rp)</th>
                <th class="py-3 px-4 text-center">Poin Loyalitas</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-base-200 text-xs">
              <!-- Loading Skeletons -->
              <tr v-if="p$charging.g$loading && p$charging.g$transactions.length === 0">
                <td colspan="9" class="text-center py-10 text-base-content/50">
                  <span class="loading loading-spinner loading-md text-primary"></span>
                  <p class="mt-2 text-xs">Memuat data transaksi charging...</p>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="p$charging.g$transactions.length === 0">
                <td colspan="9" class="text-center py-12 text-base-content/50">
                  <div class="space-y-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mx-auto text-base-content/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                    </svg>
                    <p class="font-bold text-base-content/80">Belum ada transaksi pengisian daya ditemukan</p>
                    <p class="text-xs">Data transaksi baru dari CMS atau simulasi webhook akan muncul di sini secara otomatis.</p>
                  </div>
                </td>
              </tr>

              <!-- Data Rows -->
              <tr
                v-for="trx in p$charging.g$transactions"
                :key="trx.id"
                class="hover:bg-base-200/40 transition-colors"
              >
                <!-- Waktu Selesai -->
                <td class="py-3.5 px-4 text-base-content/70 whitespace-nowrap">
                  <span class="font-medium text-base-content block">
                    {{ formatDateTime(trx.finished_at) }}
                  </span>
                  <span v-if="trx.duration_minutes" class="text-[10px] text-base-content/50">
                    Durasi: {{ trx.duration_minutes }} menit
                  </span>
                </td>

                <!-- ID Transaksi & Charger -->
                <td class="py-3.5 px-4 font-mono">
                  <span class="font-bold text-base-content block">{{ trx.external_id }}</span>
                  <span class="text-[10px] text-base-content/60">
                    {{ trx.charger_code }} <span v-if="trx.charger_type">({{ trx.charger_type }})</span>
                  </span>
                </td>

                <!-- Driver & Kendaraan -->
                <td class="py-3.5 px-4">
                  <div class="space-y-0.5">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span class="font-bold text-base-content">
                        {{ trx.customer?.name || 'Driver Tamu' }}
                      </span>
                      <BaseBadge
                        :variant="trx.customer_id ? 'primary' : 'neutral'"
                        size="xs"
                      >
                        {{ trx.customer_id ? 'Member' : 'Non-Member' }}
                      </BaseBadge>
                    </div>

                    <div class="flex items-center gap-2 text-[11px] text-base-content/60">
                      <span v-if="trx.phone" class="font-mono">{{ formatPhone(trx.phone) }}</span>
                      <span v-if="trx.plate_number" class="badge badge-xs font-mono uppercase bg-base-200">
                        {{ trx.plate_number }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Stasiun SPKLU -->
                <td class="py-3.5 px-4">
                  <span class="font-medium text-base-content block">{{ trx.station_name }}</span>
                  <span class="text-[10px] text-base-content/50 font-mono">{{ trx.station_code }}</span>
                </td>

                <!-- Daya (kWh) -->
                <td class="py-3.5 px-4 text-right font-mono font-bold text-primary">
                  {{ formatNumber(trx.kwh) }} kWh
                </td>

                <!-- Biaya (Rp) -->
                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">
                  {{ formatRupiah(trx.amount) }}
                </td>

                <!-- Poin Loyalitas -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    v-if="trx.points_awarded > 0"
                    class="badge badge-warning badge-sm font-mono font-bold gap-1 shadow-xs"
                  >
                    +{{ trx.points_awarded }} pts
                  </span>
                  <span v-else class="text-base-content/40 text-[11px]">-</span>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4 text-center">
                  <BaseBadge
                    :variant="trx.status === 'completed' ? 'success' : 'neutral'"
                    size="xs"
                  >
                    {{ trx.status }}
                  </BaseBadge>
                </td>

                <!-- Aksi -->
                <td class="py-3.5 px-4 text-right">
                  <button
                    type="button"
                    @click="handleOpenDetail(trx)"
                    class="btn btn-ghost btn-xs text-primary font-semibold hover:bg-base-200"
                    title="Lihat rincian lengkap transaksi"
                  >
                    Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Bar -->
        <div class="border-t border-base-300 p-2">
          <BasePagination
            :meta="p$charging.g$meta"
            @change="handlePageChange"
          />
        </div>
      </div>
    </div>

    <!-- Transaction Detail Modal -->
    <TransactionDetailModal
      :open="showDetailModal"
      :transaction="selectedTransaction"
      @close="showDetailModal = false"
    />
  </div>
</template>

