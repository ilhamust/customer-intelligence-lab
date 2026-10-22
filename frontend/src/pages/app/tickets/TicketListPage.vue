<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useTicketStore } from '@/stores/app/ticket.store'
import { notify } from '@/plugins'
import TicketStatusBanner from './components/TicketStatusBanner.vue'
import TicketCard from './components/TicketCard.vue'
import ServeTicketModal from './components/ServeTicketModal.vue'
import ResolveTicketModal from './components/ResolveTicketModal.vue'

const router = useRouter()
const p$ticket = useTicketStore()

// State Lokal
const searchInput = ref('')
const selectedCategory = ref('all')
const showResolveModal = ref(false)
const showServeModal = ref(false)
const activeActionTicket = ref(null)

// Kategori Enum untuk Filter
const categories = [
  { value: 'all', label: 'Semua Kategori' },
  { value: 'points_mismatch', label: 'Selisih Saldo Poin' },
  { value: 'abnormal_transaction', label: 'Transaksi Tidak Biasa / Gagal' },
  { value: 'service_complaint', label: 'Komplain Pelayanan' },
]

onMounted(async () => {
  try {
    await Promise.all([
      p$ticket.a$fetchStatus(),
      p$ticket.a$fetchList(),
    ])
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memuat data tiket')
  }

  // Subscribe ke channel Reverb untuk pembaruan realtime
  p$ticket.a$initWebSocket()
})

onUnmounted(() => {
  p$ticket.a$stopWebSocket()
})

const handleRefresh = async () => {
  try {
    await Promise.all([
      p$ticket.a$fetchStatus(),
      p$ticket.a$fetchList(),
    ])
    notify.success('Data antrean berhasil diperbarui')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memperbarui data')
  }
}

const handleSearch = (e) => {
  searchInput.value = e.target.value
  p$ticket.a$setFilter('search', e.target.value)
}

const handleCategoryChange = (e) => {
  selectedCategory.value = e.target.value
  p$ticket.a$setFilter('category', e.target.value)
}

const handleTabChange = (tab) => {
  p$ticket.a$setTab(tab)
}

// Buka obrolan percakapan di halaman Conversations
const handleOpenChat = (conversationId) => {
  if (!conversationId) return
  router.push({ path: '/app/conversations', query: { id: conversationId } })
}

// Dialog Layani Tiket
const handleOpenServeModal = (ticket) => {
  activeActionTicket.value = ticket
  showServeModal.value = true
}

const handleConfirmServe = async (adminName) => {
  if (!activeActionTicket.value) return
  try {
    const served = await p$ticket.a$serveTicket(
      activeActionTicket.value.id,
      adminName
    )
    showServeModal.value = false
    notify.success(`Mulai melayani tiket ${served.ticket_code}`)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal melayani tiket')
  }
}

// Dialog Selesaikan Tiket
const handleOpenResolveModal = (ticket) => {
  activeActionTicket.value = ticket
  showResolveModal.value = true
}

const handleConfirmResolve = async (notes) => {
  if (!activeActionTicket.value) return
  try {
    const resolved = await p$ticket.a$resolveTicket(
      activeActionTicket.value.id,
      notes
    )
    showResolveModal.value = false
    notify.success(`Tiket ${resolved.ticket_code} berhasil diselesaikan. Mode bot AI dipulihkan.`)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal menyelesaikan tiket')
  }
}

// Filtered list sesuai tab
const displayedTickets = computed(() => {
  const list = p$ticket.g$tickets
  if (p$ticket.g$activeTab === 'queued') {
    return list.filter((t) => t.status === 'queued')
  }
  if (p$ticket.g$activeTab === 'in_progress') {
    return list.filter((t) => t.status === 'in_progress')
  }
  if (p$ticket.g$activeTab === 'resolved') {
    return list.filter((t) => t.status === 'resolved')
  }
  return list
})
</script>

<template>
  <div class="h-full p-4 flex flex-col gap-4 bg-base-200 overflow-y-auto select-none">
    <!-- ─── Header Card ────────────────────────────────────────── -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5 shrink-0">
      <div class="space-y-0.5">
        <div class="flex items-center gap-2.5">
          <h1 class="text-lg font-black text-base-content tracking-tight">Support Tickets & Queue</h1>
          <BaseBadge variant="primary" pill size="sm">
            Live Escalations
          </BaseBadge>
        </div>
        <p class="text-xs text-base-content/60">
          Manajemen antrean pengaduan pelanggan & serah terima layanan Customer Care
        </p>
      </div>

      <!-- Action & Search Bar -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Search Input -->
        <div class="relative w-full sm:w-60">
          <input
            :value="searchInput"
            @input="handleSearch"
            type="text"
            placeholder="Cari kode tiket, nama, telepon..."
            class="input input-sm w-full bg-base-200 border-0 rounded-full pl-9 pr-3 text-xs text-base-content placeholder:text-base-content/40 focus:ring-1 focus:ring-base-content/20 focus:outline-none"
          />
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
        </div>

        <!-- Category Dropdown Filter -->
        <select
          :value="selectedCategory"
          @change="handleCategoryChange"
          class="select select-sm bg-base-200 border-0 rounded-xl text-xs text-base-content focus:outline-none"
        >
          <option v-for="cat in categories" :key="cat.value" :value="cat.value">
            {{ cat.label }}
          </option>
        </select>

        <!-- Refresh Button -->
        <button
          type="button"
          @click="handleRefresh"
          :disabled="p$ticket.g$loading"
          class="btn btn-sm btn-ghost btn-circle text-base-content/60 hover:text-base-content"
          title="Segarkan data tiket"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" :class="{ 'animate-spin': p$ticket.g$loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
            <path d="M16 21h5v-5" />
          </svg>
        </button>
      </div>
    </header>

    <!-- ─── Banner Status Layanan CS (Hero Indicator) ───────────── -->
    <TicketStatusBanner
      :is-busy="p$ticket.g$isBusy"
      :active-ticket="p$ticket.g$activeTicket"
      :queued-tickets="p$ticket.g$queuedTickets"
      @open-chat="handleOpenChat"
      @serve="handleOpenServeModal"
      @resolve="handleOpenResolveModal"
    />

    <!-- ─── Metric KPI Stats ────────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 shrink-0">
      <BaseStat
        label="Total Tiket"
        :value="p$ticket.g$summary.total"
        subtext="Riwayat seluruh tiket"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
            <path d="M13 5v2" /><path d="M13 17v2" /><path d="M13 11v2" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Dalam Antrean"
        :value="p$ticket.g$summary.queued"
        subtext="Menunggu respon CS"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Sedang Dilayani"
        :value="p$ticket.g$summary.in_progress"
        subtext="Ditangani langsung oleh CS"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <polyline points="16 11 18 13 22 9" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Selesai Ditangani"
        :value="p$ticket.g$summary.resolved"
        subtext="Tiket tuntas & AI pulih"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </template>
      </BaseStat>
    </div>

    <!-- ─── Tab Filter Navigation (Pill Style) ─────────────────── -->
    <div class="flex items-center gap-1.5 p-1 bg-base-100 rounded-xl border border-base-300 w-fit shrink-0">
      <button
        type="button"
        @click="handleTabChange('queued')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
        :class="[
          p$ticket.g$activeTab === 'queued'
            ? 'bg-amber-500 text-white shadow-xs'
            : 'text-base-content/60 hover:text-base-content hover:bg-base-200'
        ]"
      >
        <span>Antrean Menunggu</span>
        <span
          class="badge badge-xs font-bold"
          :class="p$ticket.g$activeTab === 'queued' ? 'badge-neutral bg-white text-amber-600' : 'badge-ghost'"
        >
          {{ p$ticket.g$summary.queued }}
        </span>
      </button>

      <button
        type="button"
        @click="handleTabChange('in_progress')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
        :class="[
          p$ticket.g$activeTab === 'in_progress'
            ? 'bg-blue-600 text-white shadow-xs'
            : 'text-base-content/60 hover:text-base-content hover:bg-base-200'
        ]"
      >
        <span>Sedang Dilayani</span>
        <span
          class="badge badge-xs font-bold"
          :class="p$ticket.g$activeTab === 'in_progress' ? 'badge-neutral bg-white text-blue-600' : 'badge-ghost'"
        >
          {{ p$ticket.g$summary.in_progress }}
        </span>
      </button>

      <button
        type="button"
        @click="handleTabChange('resolved')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
        :class="[
          p$ticket.g$activeTab === 'resolved'
            ? 'bg-emerald-600 text-white shadow-xs'
            : 'text-base-content/60 hover:text-base-content hover:bg-base-200'
        ]"
      >
        <span>Riwayat Selesai</span>
        <span
          class="badge badge-xs font-bold"
          :class="p$ticket.g$activeTab === 'resolved' ? 'badge-neutral bg-white text-emerald-600' : 'badge-ghost'"
        >
          {{ p$ticket.g$summary.resolved }}
        </span>
      </button>

      <button
        type="button"
        @click="handleTabChange('all')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all"
        :class="[
          p$ticket.g$activeTab === 'all'
            ? 'bg-primary text-primary-content shadow-xs'
            : 'text-base-content/60 hover:text-base-content hover:bg-base-200'
        ]"
      >
        <span>Semua Tiket</span>
        <span
          class="badge badge-xs font-bold"
          :class="p$ticket.g$activeTab === 'all' ? 'badge-neutral bg-white text-primary' : 'badge-ghost'"
        >
          {{ p$ticket.g$summary.total }}
        </span>
      </button>
    </div>

    <!-- ─── Content Section ────────────────────────────────────── -->
    <!-- Loading State -->
    <div v-if="p$ticket.g$loading" class="flex flex-col items-center justify-center py-20 bg-base-100 rounded-2xl border border-base-300">
      <BaseLoading size="lg" text="Memuat daftar tiket & antrean..." />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="displayedTickets.length === 0"
      class="bg-base-100 rounded-2xl border border-base-300 p-12 flex flex-col items-center justify-center text-center"
    >
      <BaseEmpty
        :title="
          p$ticket.g$activeTab === 'queued'
            ? 'Tidak Ada Antrean Menunggu'
            : p$ticket.g$activeTab === 'in_progress'
              ? 'Tidak Ada Tiket yang Sedang Dilayani'
              : 'Belum Ada Tiket yang Cocok'
        "
        :description="
          p$ticket.g$activeTab === 'queued'
            ? 'Semua pengaduan pelanggan telah selesai ditangani atau sedang dalam proses.'
            : 'Semua antrean saat ini berada dalam kondisi aman dan tertangani.'
        "
      />
    </div>

    <!-- Ticket Cards List / Grid -->
    <div v-else class="space-y-3">
      <TicketCard
        v-for="ticket in displayedTickets"
        :key="ticket.id"
        :ticket="ticket"
        :action-loading="p$ticket.g$actionLoading"
        @open-chat="handleOpenChat"
        @serve="handleOpenServeModal"
        @resolve="handleOpenResolveModal"
      />
    </div>

    <!-- ─── Modals ─────────────────────────────────────────────── -->
    <ServeTicketModal
      v-model:open="showServeModal"
      :ticket="activeActionTicket"
      :loading="p$ticket.g$actionLoading"
      @confirm="handleConfirmServe"
    />

    <ResolveTicketModal
      v-model:open="showResolveModal"
      :ticket="activeActionTicket"
      :loading="p$ticket.g$actionLoading"
      @confirm="handleConfirmResolve"
    />
  </div>
</template>
