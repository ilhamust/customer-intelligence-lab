<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useStaffStore } from '@/stores/app/staff.store'
import { notify } from '@/plugins'
import StaffModal from './components/StaffModal.vue'
import ActiveAlertsModal from './components/ActiveAlertsModal.vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseBadge from '@/components/BaseBadge.vue'
import BaseDialog from '@/components/BaseDialog.vue'

const p$staff = useStaffStore()

// State Lokal
const searchInput = ref('')
const selectedRole = ref('all')
const selectedDuty = ref('all')
const showStaffModal = ref(false)
const showActiveAlertsModal = ref(false)
const activeStaffData = ref(null)

// Delete Dialog
const showDeleteDialog = ref(false)
const staffToDelete = ref(null)

const roleOptions = [
  { value: 'all', label: 'Semua Peran' },
  { value: 'field_technician', label: 'Teknisi Lapangan' },
  { value: 'site_operator', label: 'Operator Lokasi' },
  { value: 'station_manager', label: 'Supervisor / Manajer Stasiun' },
]

onMounted(async () => {
  try {
    await Promise.all([
      p$staff.a$fetchList(),
      p$staff.a$fetchActiveAlerts(),
    ])
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memuat daftar kontak staf')
  }

  // Subscribe ke channel Reverb untuk notifikasi real-time
  p$staff.a$initWebSocket()
})

onUnmounted(() => {
  p$staff.a$stopWebSocket()
})

const handleRefresh = async () => {
  try {
    await Promise.all([
      p$staff.a$fetchList(),
      p$staff.a$fetchActiveAlerts(),
    ])
    notify.success('Data staf dan status darurat diperbarui')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memperbarui data')
  }
}

const handleSearch = (e) => {
  searchInput.value = e.target.value
  p$staff.a$setFilter('search', e.target.value)
}

const handleRoleChange = (e) => {
  selectedRole.value = e.target.value
  p$staff.a$setFilter('role', e.target.value)
}

const handleDutyFilter = (val) => {
  selectedDuty.value = val
  p$staff.a$setFilter('is_on_duty', val)
}

// Modal Form Tambah
const handleOpenCreateModal = () => {
  activeStaffData.value = null
  showStaffModal.value = true
}

// Modal Form Edit
const handleOpenEditModal = (staff) => {
  activeStaffData.value = { ...staff }
  showStaffModal.value = true
}

// Toggle Duty Status Instan
const handleToggleDuty = async (staff) => {
  try {
    const updated = await p$staff.a$toggleDuty(staff.id)
    const statusText = updated.is_on_duty ? 'On-Duty (Siaga)' : 'Off-Duty (Libur)'
    notify.success(`Status ${updated.name} diubah menjadi ${statusText}`)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal mengubah status tugas')
  }
}

// Dialog Hapus
const handleConfirmDelete = (staff) => {
  staffToDelete.value = staff
  showDeleteDialog.value = true
}

const handleExecuteDelete = async () => {
  if (!staffToDelete.value) return
  try {
    await p$staff.a$deleteStaff(staffToDelete.value.id)
    notify.success(`Kontak ${staffToDelete.value.name} berhasil dihapus`)
    showDeleteDialog.value = false
    staffToDelete.value = null
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal menghapus kontak staf')
  }
}

const getRoleBadge = (role) => {
  switch (role) {
    case 'field_technician':
      return { label: 'Teknisi Lapangan', variant: 'primary' }
    case 'site_operator':
      return { label: 'Operator Lokasi', variant: 'secondary' }
    case 'station_manager':
      return { label: 'Supervisor / Manager', variant: 'accent' }
    default:
      return { label: role, variant: 'neutral' }
  }
}

const formatPhoneDisplay = (phone) => {
  if (!phone) return '-'
  if (phone.startsWith('62')) {
    return `+${phone.slice(0, 2)} ${phone.slice(2, 5)}-${phone.slice(5, 9)}-${phone.slice(9)}`
  }
  return phone
}
</script>

<template>
  <div class="h-full flex flex-col overflow-hidden bg-base-200">
    <!-- Top Header Bar -->
    <header class="bg-base-100 border-b border-base-300 px-6 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4 shrink-0">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-lg font-black text-base-content tracking-tight">
            Petugas Lapangan & Emergency Guardrail 24/7
          </h1>
          <BaseBadge variant="primary" size="xs">Sprint 2</BaseBadge>
        </div>
        <p class="text-xs text-base-content/60 mt-0.5">
          Manajemen staf darurat penerima broadcast WhatsApp saat terdeteksi insiden fisik/kabel terkunci/korsleting di SPKLU.
        </p>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <!-- Monitor Active Alerts Button -->
        <button
          @click="showActiveAlertsModal = true"
          class="btn btn-sm text-xs font-bold gap-2 shadow-xs transition-all"
          :class="p$staff.g$hasActiveAlerts
            ? 'btn-error text-white animate-pulse'
            : 'btn-outline border-base-300 text-base-content/80 hover:bg-base-200'"
        >
          <span class="w-2 h-2 rounded-full" :class="p$staff.g$hasActiveAlerts ? 'bg-white' : 'bg-success'"></span>
          <span>Insiden Darurat</span>
          <span
            v-if="p$staff.g$hasActiveAlerts"
            class="badge badge-xs bg-white text-error font-mono font-bold"
          >
            {{ p$staff.g$activeAlertsCount }}
          </span>
        </button>

        <!-- Refresh Button -->
        <BaseButton
          variant="outline"
          size="sm"
          :loading="p$staff.g$loading"
          @click="handleRefresh"
          title="Segarkan data"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
            <path d="M16 21h5v-5" />
          </svg>
        </BaseButton>

        <!-- Tambah Petugas Button -->
        <BaseButton
          variant="primary"
          size="sm"
          @click="handleOpenCreateModal"
          class="gap-1.5"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="22" y1="11" x2="16" y2="11" />
          </svg>
          Tambah Petugas
        </BaseButton>
      </div>
    </header>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-6 space-y-5">
      <!-- KPI Metrics Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Total Staf -->
        <div class="bg-base-100 rounded-2xl border border-base-300 p-4 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-base-content/60">
            <span class="text-[11px] font-bold uppercase tracking-wider">Total Petugas</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
          <p class="text-2xl font-black text-base-content">{{ p$staff.g$summary.total_staff }}</p>
          <span class="text-[10px] text-base-content/50">Terdaftar di database</span>
        </div>

        <!-- On-Duty (Siaga 24/7) -->
        <div class="bg-base-100 rounded-2xl border border-success/30 p-4 shadow-xs space-y-1 bg-success/5">
          <div class="flex items-center justify-between text-success">
            <span class="text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
              On-Duty (Siaga)
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
            </svg>
          </div>
          <p class="text-2xl font-black text-success">{{ p$staff.g$summary.on_duty_count }}</p>
          <span class="text-[10px] text-success/70">Penerima broadcast darurat aktif</span>
        </div>

        <!-- Off-Duty (Libur/Istirahat) -->
        <div class="bg-base-100 rounded-2xl border border-base-300 p-4 shadow-xs space-y-1">
          <div class="flex items-center justify-between text-base-content/60">
            <span class="text-[11px] font-bold uppercase tracking-wider">Off-Duty (Libur)</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10" />
              <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" />
            </svg>
          </div>
          <p class="text-2xl font-black text-base-content/70">{{ p$staff.g$summary.off_duty_count }}</p>
          <span class="text-[10px] text-base-content/50">Tidak menerima alert sementara</span>
        </div>

        <!-- Active Emergency Incidents -->
        <div
          class="bg-base-100 rounded-2xl border p-4 shadow-xs space-y-1 cursor-pointer transition-all hover:scale-[1.01]"
          :class="p$staff.g$hasActiveAlerts ? 'border-error bg-error/10' : 'border-base-300'"
          @click="showActiveAlertsModal = true"
        >
          <div class="flex items-center justify-between" :class="p$staff.g$hasActiveAlerts ? 'text-error' : 'text-base-content/60'">
            <span class="text-[11px] font-bold uppercase tracking-wider flex items-center gap-1.5">
              <span v-if="p$staff.g$hasActiveAlerts" class="w-2 h-2 rounded-full bg-error animate-ping"></span>
              Alert Aktif
            </span>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
          </div>
          <p class="text-2xl font-black" :class="p$staff.g$hasActiveAlerts ? 'text-error' : 'text-base-content'">
            {{ p$staff.g$activeAlertsCount }}
          </p>
          <span class="text-[10px]" :class="p$staff.g$hasActiveAlerts ? 'text-error/80 font-bold' : 'text-base-content/50'">
            {{ p$staff.g$hasActiveAlerts ? 'Klik untuk tinjau insiden' : 'Kondisi stasiun aman' }}
          </span>
        </div>
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
              placeholder="Cari nama staf, nomor WhatsApp, atau stasiun..."
              class="input input-sm w-full pl-10 rounded-xl bg-base-200/60 border-base-300 text-xs focus:outline-primary"
            />
          </div>

          <!-- Quick Filters Group -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <!-- Role Selector -->
            <select
              :value="selectedRole"
              @change="handleRoleChange"
              class="select select-bordered select-sm rounded-xl text-xs bg-base-100 focus:outline-primary"
            >
              <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>

            <!-- On-Duty Segment Buttons -->
            <div class="join border border-base-300 rounded-xl overflow-hidden bg-base-200/50 p-0.5">
              <button
                @click="handleDutyFilter('all')"
                class="join-item btn btn-xs border-none rounded-lg text-[11px]"
                :class="selectedDuty === 'all' ? 'btn-neutral shadow-xs' : 'btn-ghost text-base-content/60'"
              >
                Semua
              </button>
              <button
                @click="handleDutyFilter('true')"
                class="join-item btn btn-xs border-none rounded-lg text-[11px]"
                :class="selectedDuty === 'true' ? 'btn-success text-white shadow-xs' : 'btn-ghost text-base-content/60'"
              >
                On-Duty
              </button>
              <button
                @click="handleDutyFilter('false')"
                class="join-item btn btn-xs border-none rounded-lg text-[11px]"
                :class="selectedDuty === 'false' ? 'btn-neutral shadow-xs' : 'btn-ghost text-base-content/60'"
              >
                Off-Duty
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Staff Table Card -->
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
          <table class="table table-sm w-full">
            <thead class="bg-base-200/60 border-b border-base-300 text-base-content/60 text-[11px] uppercase tracking-wider">
              <tr>
                <th class="py-3 px-4">Nama Petugas</th>
                <th class="py-3 px-4">Nomor WhatsApp</th>
                <th class="py-3 px-4">Peran / Posisi</th>
                <th class="py-3 px-4">Penempatan Stasiun</th>
                <th class="py-3 px-4 text-center">Status Siaga (Piket)</th>
                <th class="py-3 px-4 text-center">Keaktifan</th>
                <th class="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-base-200 text-xs">
              <!-- Loading Skeletons -->
              <tr v-if="p$staff.g$loading && p$staff.g$staffList.length === 0">
                <td colspan="7" class="text-center py-10 text-base-content/50">
                  <span class="loading loading-spinner loading-md text-primary"></span>
                  <p class="mt-2 text-xs">Memuat daftar staf lapangan...</p>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="p$staff.g$staffList.length === 0">
                <td colspan="7" class="text-center py-12 text-base-content/50">
                  <div class="space-y-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 mx-auto text-base-content/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                      <circle cx="9" cy="7" r="4" />
                      <line x1="18" y1="8" x2="23" y2="13" />
                      <line x1="23" y1="8" x2="18" y2="13" />
                    </svg>
                    <p class="font-bold text-base-content/80">Belum ada kontak staf ditemukan</p>
                    <p class="text-xs">Tambahkan petugas baru agar notifikasi darurat 24/7 dapat diteruskan.</p>
                  </div>
                </td>
              </tr>

              <!-- Data Rows -->
              <tr
                v-for="staff in p$staff.g$staffList"
                :key="staff.id"
                class="hover:bg-base-200/40 transition-colors"
              >
                <!-- Nama & Catatan -->
                <td class="py-3.5 px-4 font-semibold text-base-content">
                  <div class="space-y-0.5">
                    <div class="font-bold flex items-center gap-1.5">
                      <span>{{ staff.name }}</span>
                    </div>
                    <p v-if="staff.notes" class="text-[11px] text-base-content/50 font-normal italic">
                      {{ staff.notes }}
                    </p>
                  </div>
                </td>

                <!-- Nomor WA -->
                <td class="py-3.5 px-4 font-mono">
                  <a
                    :href="`https://wa.me/${staff.phone}`"
                    target="_blank"
                    class="link link-hover text-primary font-semibold flex items-center gap-1.5"
                    title="Kirim pesan WhatsApp langsung"
                  >
                    <span>{{ formatPhoneDisplay(staff.phone) }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-success shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                      <polyline points="15 3 21 3 21 9" />
                      <line x1="10" y1="14" x2="21" y2="3" />
                    </svg>
                  </a>
                </td>

                <!-- Peran -->
                <td class="py-3.5 px-4">
                  <BaseBadge :variant="getRoleBadge(staff.role).variant" size="xs">
                    {{ getRoleBadge(staff.role).label }}
                  </BaseBadge>
                </td>

                <!-- Stasiun -->
                <td class="py-3.5 px-4">
                  <span
                    class="badge badge-sm font-medium"
                    :class="staff.station_name === 'all' ? 'badge-ghost text-base-content/70' : 'badge-neutral'"
                  >
                    {{ staff.station_name === 'all' ? '🌐 Semua Stasiun' : staff.station_name }}
                  </span>
                </td>

                <!-- Toggle Status Piket (Interactive One-Click Button) -->
                <td class="py-3.5 px-4 text-center">
                  <button
                    @click="handleToggleDuty(staff)"
                    class="btn btn-xs rounded-full gap-1.5 font-bold transition-all shadow-xs"
                    :class="staff.is_on_duty
                      ? 'btn-success text-white hover:brightness-95'
                      : 'btn-ghost bg-base-200 text-base-content/40 hover:bg-base-300 hover:text-base-content'"
                    :title="staff.is_on_duty ? 'Klik untuk mengubah menjadi Off-Duty' : 'Klik untuk mengaktifkan On-Duty (Siaga)'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="staff.is_on_duty ? 'bg-white animate-pulse' : 'bg-base-content/40'"></span>
                    <span>{{ staff.is_on_duty ? 'On-Duty' : 'Off-Duty' }}</span>
                  </button>
                </td>

                <!-- Status Aktif Kepegawaian -->
                <td class="py-3.5 px-4 text-center">
                  <span
                    class="inline-block w-2 h-2 rounded-full mr-1.5"
                    :class="staff.is_active ? 'bg-success' : 'bg-base-content/30'"
                  ></span>
                  <span class="text-[11px] font-medium" :class="staff.is_active ? 'text-base-content' : 'text-base-content/40'">
                    {{ staff.is_active ? 'Aktif' : 'Non-Aktif' }}
                  </span>
                </td>

                <!-- Aksi -->
                <td class="py-3.5 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      @click="handleOpenEditModal(staff)"
                      class="btn btn-ghost btn-xs btn-circle text-base-content/60 hover:text-primary hover:bg-base-200"
                      title="Edit kontak staf"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
                        <path d="m15 5 4 4" />
                      </svg>
                    </button>

                    <button
                      type="button"
                      @click="handleConfirmDelete(staff)"
                      class="btn btn-ghost btn-xs btn-circle text-base-content/60 hover:text-error hover:bg-error/10"
                      title="Hapus kontak staf"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <StaffModal
      :open="showStaffModal"
      :staff-data="activeStaffData"
      @close="showStaffModal = false"
      @saved="handleRefresh"
    />

    <ActiveAlertsModal
      :open="showActiveAlertsModal"
      @close="showActiveAlertsModal = false"
    />

    <BaseDialog
      :open="showDeleteDialog"
      title="Hapus Kontak Petugas?"
      :message="`Apakah Anda yakin ingin menghapus petugas ${staffToDelete?.name}? Nomor ini tidak akan menerima alert WhatsApp darurat lagi.`"
      confirm-text="Ya, Hapus"
      cancel-text="Batal"
      variant="error"
      :loading="p$staff.g$actionLoading"
      @confirm="handleExecuteDelete"
      @cancel="showDeleteDialog = false"
    />
  </div>
</template>

