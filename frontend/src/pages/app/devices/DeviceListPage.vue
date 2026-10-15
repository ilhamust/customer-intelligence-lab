<script setup>
import { onMounted } from 'vue'
import { useDeviceStore } from '@/stores/app/device.store'
import { notify } from '@/plugins'

const p$device = useDeviceStore()

onMounted(async () => {
  try {
    await p$device.a$fetchList()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memuat daftar perangkat')
  }
})
</script>

<template>
  <div class="h-full p-4 flex flex-col gap-4 bg-base-200 overflow-y-auto select-none">
    <!-- Header Section -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5">
      <div>
        <h1 class="text-lg font-black text-base-content tracking-tight">Devices</h1>
        <p class="text-xs text-base-content/60 mt-0.5">WhatsApp accounts connected to this server</p>
      </div>

      <div class="flex items-center gap-2">
        <BaseButton variant="primary" size="sm">
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

    <!-- Main Content Area -->
    <main class="flex-1">
      <div v-if="p$device.g$loading" class="bg-base-100 rounded-2xl border border-base-300 p-8 shadow-xs">
        <BaseLoading text="Memuat perangkat WhatsApp..." />
      </div>

      <div v-else-if="p$device.g$devices.length === 0" class="bg-base-100 rounded-2xl border border-base-300 p-8 shadow-xs">
        <BaseEmpty
          title="No devices found"
          description="Belum ada perangkat WhatsApp yang terhubung ke server ini."
        >
          <template #action>
            <BaseButton variant="primary" size="sm">
              <template #prefix>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M5 12h14" />
                  <path d="M12 5v14" />
                </svg>
              </template>
              Add device
            </BaseButton>
          </template>
        </BaseEmpty>
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Devices will be rendered here in Step 4 -->
      </div>
    </main>
  </div>
</template>
