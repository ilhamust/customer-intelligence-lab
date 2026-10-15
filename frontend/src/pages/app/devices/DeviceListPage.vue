<script setup>
import { onMounted, ref } from 'vue'
import { useDeviceStore } from '@/stores/app/device.store'
import { formatDateTime, formatPhone } from '@/utils/helper'
import { notify } from '@/plugins'
import DeviceCreateModal from './components/DeviceCreateModal.vue'
import DeviceQrModal from './components/DeviceQrModal.vue'
import DeviceCodeModal from './components/DeviceCodeModal.vue'

const p$device = useDeviceStore()
const searchInput = ref('')

// Modal state placeholders for Step 5
const showAddModal = ref(false)
const showQrModal = ref(false)
const showCodeModal = ref(false)
const activeDevice = ref(null)

// Confirmation Delete Dialog
const showDeleteDialog = ref(false)
const deviceToDelete = ref(null)
const deleteLoading = ref(false)

onMounted(async () => {
  await fetchDevices()
})

const fetchDevices = async () => {
  try {
    await p$device.a$fetchList()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to load devices')
  }
}

const handleSearch = (e) => {
  searchInput.value = e.target.value
  p$device.a$search(e.target.value)
}

const handleReconnect = async (device) => {
  try {
    await p$device.a$reconnect(device.device_id)
    notify.success(`Reconnecting device ${device.name || device.device_id}`)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to reconnect device')
  }
}

const handleLogout = async (device) => {
  try {
    await p$device.a$logout(device.device_id)
    notify.success(`WhatsApp session for ${device.name || device.device_id} logged out successfully`)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to log out device')
  }
}

const handleWebhook = (device) => {
  notify.info(`Webhook active for slot: ${device.device_id}`)
}

const confirmDelete = (device) => {
  deviceToDelete.value = device
  showDeleteDialog.value = true
}

const executeDelete = async () => {
  if (!deviceToDelete.value) return
  deleteLoading.value = true
  try {
    await p$device.a$delete(deviceToDelete.value.device_id)
    notify.success(`Device ${deviceToDelete.value.name || deviceToDelete.value.device_id} successfully deleted`)
    showDeleteDialog.value = false
    deviceToDelete.value = null
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to delete device')
  } finally {
    deleteLoading.value = false
  }
}

const handleOpenQr = (device) => {
  activeDevice.value = device
  showQrModal.value = true
}

const handleOpenCode = (device) => {
  activeDevice.value = device
  showCodeModal.value = true
}
</script>

<template>
  <div class="h-full p-4 flex flex-col gap-4 bg-base-200 overflow-y-auto select-none">
    <!-- ─── Header Floating Card ───────────────────────────────── -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5 shrink-0">
      <div class="space-y-0.5">
        <div class="flex items-center gap-2.5">
          <h1 class="text-lg font-black text-base-content tracking-tight">Devices</h1>
          <BaseBadge v-if="p$device.g$devices.length > 0" variant="neutral" pill size="sm">
            {{ p$device.g$connectedCount }}/{{ p$device.g$devices.length }} Online
          </BaseBadge>
        </div>
        <p class="text-xs text-base-content/60">WhatsApp accounts connected to this server</p>
      </div>

      <!-- Action & Search Bar -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Search Input Pill -->
        <div class="relative w-full sm:w-64">
          <input
            :value="searchInput"
            @input="handleSearch"
            type="text"
            placeholder="Search devices..."
            class="input input-sm w-full bg-base-200 border-0 rounded-full pl-9 pr-3 text-xs text-base-content placeholder:text-base-content/40 focus:ring-1 focus:ring-base-content/20 focus:outline-none"
          />
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.3-4.3" />
          </svg>
        </div>

        <!-- Reload Button -->
        <button
          type="button"
          @click="fetchDevices"
          :disabled="p$device.g$loading"
          class="btn btn-sm btn-ghost btn-circle text-base-content/60 hover:text-base-content"
          title="Refresh list"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" :class="{ 'animate-spin': p$device.g$loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
            <path d="M16 21h5v-5" />
          </svg>
        </button>

        <!-- Add Device CTA Button -->
        <BaseButton variant="primary" size="sm" @click="showAddModal = true">
          <template #prefix>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14" />
              <path d="M12 5v14" />
            </svg>
          </template>
          Add device
        </BaseButton>
      </div>
    </header>

    <!-- ─── Content Body ──────────────────────────────────────── -->
    <main class="flex-1">
      <!-- State 1: Loading -->
      <div v-if="p$device.g$loading && p$device.g$devices.length === 0" class="bg-base-100 rounded-2xl border border-base-300 p-12 shadow-xs flex items-center justify-center">
        <BaseLoading text="Loading WhatsApp devices..." />
      </div>

      <!-- State 2: Empty -->
      <div v-else-if="p$device.g$filteredDevices.length === 0" class="bg-base-100 rounded-2xl border border-base-300 p-12 shadow-xs">
        <BaseEmpty
          :title="searchInput ? 'No devices found' : 'No devices found'"
          :description="searchInput ? 'No WhatsApp devices match your search query.' : 'No WhatsApp accounts have been connected to this gateway yet.'"
        >
          <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect width="14" height="20" x="5" y="2" rx="2" ry="2" />
              <path d="M12 18h.01" />
            </svg>
          </template>
          <template #action>
            <BaseButton v-if="!searchInput" variant="primary" size="sm" @click="showAddModal = true">
              <template #prefix>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14" />
                  <path d="M12 5v14" />
                </svg>
              </template>
              Add device
            </BaseButton>
            <BaseButton v-else variant="outline" size="sm" @click="handleSearch({ target: { value: '' } })">
              Reset Search
            </BaseButton>
          </template>
        </BaseEmpty>
      </div>

      <!-- State 3: Cards Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="device in p$device.g$filteredDevices"
          :key="device.device_id"
          class="bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5 relative flex flex-col justify-between hover:shadow-sm transition-all duration-150"
        >
          <!-- Card Top: Avatar, Info, Status Badge -->
          <div>
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <!-- Squircle Icon Container -->
                <div class="w-11 h-11 rounded-2xl bg-base-200 border border-base-300 flex items-center justify-center text-base-content/70 shrink-0 shadow-xs">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="14" height="20" x="5" y="2" rx="2" ry="2" />
                    <path d="M12 18h.01" />
                  </svg>
                </div>

                <!-- Text Header -->
                <div class="min-w-0">
                  <h3 class="font-bold text-sm text-base-content truncate tracking-tight" :title="device.phone_number || device.jid || device.name">
                    {{ device.phone_number ? formatPhone(device.phone_number) : (device.name || 'WhatsApp Gateway') }}
                  </h3>
                  <p class="text-xs text-base-content/60 truncate mt-0.5" :title="device.jid || device.name">
                    {{ device.jid || device.name || 'Slot ID: ' + device.device_id }}
                  </p>
                </div>
              </div>

              <!-- Status Badge -->
              <BaseBadge
                :variant="device.status === 'connected' ? 'success' : device.status === 'pairing' ? 'warning' : 'neutral'"
                pill
                size="sm"
                class="shrink-0"
              >
                {{ device.status === 'connected' ? 'Logged in' : device.status === 'pairing' ? 'Pairing' : 'Disconnected' }}
              </BaseBadge>
            </div>

            <!-- Card Middle: Unified ID & Created Date -->
            <div class="mt-4 pt-3.5 pb-1 border-t border-base-300/80 space-y-1">
              <p class="text-[11px] text-base-content/60 truncate select-all">
                <span class="text-base-content/40 font-medium">ID:</span> <span class="font-semibold text-base-content/75">{{ device.device_id }}</span>
              </p>
              <p class="text-[11px] text-base-content/50">
                Created {{ formatDateTime(device.created_at) }}
              </p>
            </div>
          </div>

          <!-- Card Bottom: Selected State & Action Dropdown Menu -->
          <div class="mt-4 pt-3 border-t border-base-300 flex items-center justify-between">
            <!-- Selected Indicator Pill -->
            <div>
              <BaseBadge
                v-if="p$device.g$selectedDeviceId === device.device_id"
                variant="primary"
                pill
                size="xs"
              >
                Selected
              </BaseBadge>
              <button
                v-else
                type="button"
                @click="p$device.a$select(device.device_id)"
                class="text-xs font-semibold text-base-content/50 hover:text-base-content transition-colors cursor-pointer"
              >
                Select
              </button>
            </div>

            <!-- Three Dots Action Dropdown (Ala Gambar 3) -->
            <div class="dropdown dropdown-top dropdown-end">
              <button
                tabindex="0"
                type="button"
                class="btn btn-ghost btn-sm btn-circle text-base-content/60 hover:text-base-content"
                title="Device actions"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="1" />
                  <circle cx="12" cy="5" r="1" />
                  <circle cx="12" cy="19" r="1" />
                </svg>
              </button>

              <ul
                tabindex="0"
                class="dropdown-content menu z-30 p-1.5 shadow-xl bg-base-100 rounded-2xl border border-base-300 w-44 text-xs font-medium space-y-0.5"
              >
                <li>
                  <button type="button" @click="handleOpenQr(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 hover:bg-base-200 text-base-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <rect width="5" height="5" x="3" y="3" rx="1" />
                      <rect width="5" height="5" x="16" y="3" rx="1" />
                      <rect width="5" height="5" x="3" y="16" rx="1" />
                      <path d="M21 16h-3a2 2 0 0 0-2 2v3" />
                      <path d="M21 21v.01" />
                      <path d="M12 7v3a2 2 0 0 1-2 2H7" />
                      <path d="M3 12h.01" />
                      <path d="M12 3h.01" />
                      <path d="M12 16v.01" />
                      <path d="M16 12h1" />
                      <path d="M21 12v.01" />
                      <path d="M12 21v-1" />
                    </svg>
                    <span>Login with QR</span>
                  </button>
                </li>
                <li>
                  <button type="button" @click="handleOpenCode(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 hover:bg-base-200 text-base-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="m21 2-2 2m-1.5 1.5L16 7l-2-2m-1.5 1.5L11 8l-2-2" />
                      <circle cx="7.5" cy="15.5" r="5.5" />
                      <path d="m11.5 11.5 8.5-8.5" />
                    </svg>
                    <span>Login with code</span>
                  </button>
                </li>
                <li>
                  <button type="button" @click="handleReconnect(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 hover:bg-base-200 text-base-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                      <path d="M21 3v5h-5" />
                      <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                      <path d="M8 21H3v-5" />
                    </svg>
                    <span>Reconnect</span>
                  </button>
                </li>
                <li>
                  <button type="button" @click="handleLogout(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 hover:bg-base-200 text-base-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M19 12H5" />
                      <path d="m12 19-7-7 7-7" />
                    </svg>
                    <span>Logout</span>
                  </button>
                </li>
                <li>
                  <button type="button" @click="handleWebhook(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 hover:bg-base-200 text-base-content">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                      <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                    </svg>
                    <span>Webhook</span>
                  </button>
                </li>
                <div class="h-px bg-base-300 my-1"></div>
                <li>
                  <button type="button" @click="confirmDelete(device)" class="flex items-center gap-2.5 rounded-xl py-2 px-3 text-error hover:bg-error/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 6h18" />
                      <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                      <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                    </svg>
                    <span>Delete</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- ─── BaseDialog for Delete Confirmation ───────────────── -->
    <BaseDialog
      :open="showDeleteDialog"
      variant="error"
      title="Delete WhatsApp Device"
      :message="`Are you sure you want to delete '${deviceToDelete?.name || deviceToDelete?.device_id}'? This action will remove the WhatsApp session from the gateway server.`"
      confirm-text="Delete Device"
      cancel-text="Cancel"
      :loading="deleteLoading"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />

    <!-- ─── Modals with Blurred Backdrop ─────────────────────── -->
    <DeviceCreateModal
      :open="showAddModal"
      @close="showAddModal = false"
      @created="fetchDevices"
    />

    <DeviceQrModal
      :open="showQrModal"
      :device="activeDevice"
      @close="showQrModal = false"
    />

    <DeviceCodeModal
      :open="showCodeModal"
      :device="activeDevice"
      @close="showCodeModal = false"
    />
  </div>
</template>
