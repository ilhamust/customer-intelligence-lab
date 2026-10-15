// src/stores/app/device.store.js
import { ref, computed } from 'vue'
import { defineStore, acceptHMRUpdate } from 'pinia'
import * as s$device from '@/services/app/device.service'

export const useDeviceStore = defineStore('device', () => {
  // ─── State ───────────────────────────────────────────
  const devices = ref([])
  const selectedDeviceId = ref(null)
  const searchQuery = ref('')
  const loading = ref(false)

  // ─── Getters ─────────────────────────────────────────
  const g$devices = computed(() => devices.value)
  const g$loading = computed(() => loading.value)
  const g$selectedDeviceId = computed(() => selectedDeviceId.value)

  const g$selectedDevice = computed(() =>
    devices.value.find((d) => d.device_id === selectedDeviceId.value) || devices.value[0] || null
  )

  const g$filteredDevices = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return devices.value

    return devices.value.filter(
      (d) =>
        (d.name && d.name.toLowerCase().includes(q)) ||
        (d.device_id && d.device_id.toLowerCase().includes(q)) ||
        (d.phone_number && d.phone_number.includes(q)) ||
        (d.jid && d.jid.toLowerCase().includes(q))
    )
  })

  const g$connectedCount = computed(
    () => devices.value.filter((d) => d.status === 'connected').length
  )

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async () => {
    loading.value = true
    try {
      const res = await s$device.list()
      const listData = res?.data ?? res ?? []
      devices.value = Array.isArray(listData) ? listData : []

      // Otomatis pilih device pertama jika belum ada yang dipilih
      if (!selectedDeviceId.value && devices.value.length > 0) {
        selectedDeviceId.value = devices.value[0].device_id
      }
      return devices.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$register = async (payload) => {
    try {
      const res = await s$device.register(payload)
      await a$fetchList()
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$select = (deviceId) => {
    selectedDeviceId.value = deviceId
  }

  const a$getLoginQr = async (deviceId) => {
    try {
      const res = await s$device.loginQr(deviceId)
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$getLoginCode = async (deviceId, phone) => {
    try {
      const res = await s$device.loginCode({ device_id: deviceId, phone })
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$logout = async (deviceId) => {
    try {
      const res = await s$device.logout(deviceId)
      // Update status lokal
      const target = devices.value.find((d) => d.device_id === deviceId)
      if (target) {
        target.status = 'disconnected'
        target.jid = null
      }
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$reconnect = async (deviceId) => {
    try {
      const res = await s$device.reconnect(deviceId)
      // Re-fetch status device
      await a$fetchList()
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$delete = async (deviceId) => {
    try {
      const res = await s$device.destroy(deviceId)
      devices.value = devices.value.filter((d) => d.device_id !== deviceId)
      if (selectedDeviceId.value === deviceId) {
        selectedDeviceId.value = devices.value[0]?.device_id || null
      }
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$search = (query) => {
    searchQuery.value = query
  }

  return {
    // state
    devices,
    selectedDeviceId,
    searchQuery,
    loading,
    // getters
    g$devices,
    g$loading,
    g$selectedDeviceId,
    g$selectedDevice,
    g$filteredDevices,
    g$connectedCount,
    // actions
    a$fetchList,
    a$register,
    a$select,
    a$getLoginQr,
    a$getLoginCode,
    a$logout,
    a$reconnect,
    a$delete,
    a$search,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useDeviceStore, import.meta.hot))
}
