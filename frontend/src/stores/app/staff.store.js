// src/stores/app/staff.store.js
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as s$staff from '@/services/app/staff.service'
import { echo } from '@/plugins/echo'

export const useStaffStore = defineStore('staff', () => {
  // ─── State ───────────────────────────────────────────
  const staffList = ref([])
  const summary = ref({
    total_staff: 0,
    active_count: 0,
    on_duty_count: 0,
    off_duty_count: 0,
  })
  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
  })
  const activeAlerts = ref([])
  const activeAlertsCount = ref(0)
  const loading = ref(false)
  const actionLoading = ref(false)
  const alertLoading = ref(false)

  const filters = ref({
    search: '',
    role: 'all',
    is_on_duty: 'all',
    is_active: 'all',
    per_page: 20,
  })

  // ─── Getters ─────────────────────────────────────────
  const g$staffList = computed(() => staffList.value)
  const g$summary = computed(() => summary.value)
  const g$meta = computed(() => meta.value)
  const g$activeAlerts = computed(() => activeAlerts.value)
  const g$activeAlertsCount = computed(() => activeAlertsCount.value)
  const g$hasActiveAlerts = computed(() => activeAlertsCount.value > 0)
  const g$loading = computed(() => loading.value)
  const g$actionLoading = computed(() => actionLoading.value)
  const g$filters = computed(() => filters.value)

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async (customParams = {}) => {
    loading.value = true
    try {
      const params = {
        search: filters.value.search || undefined,
        role: filters.value.role !== 'all' ? filters.value.role : undefined,
        is_on_duty: filters.value.is_on_duty !== 'all' ? filters.value.is_on_duty : undefined,
        is_active: filters.value.is_active !== 'all' ? filters.value.is_active : undefined,
        per_page: filters.value.per_page,
        ...customParams,
      }

      const res = await s$staff.list(params)
      const dataPayload = res?.data ?? res ?? {}

      staffList.value = dataPayload.data || []
      if (dataPayload.summary) {
        summary.value = dataPayload.summary
      }
      if (dataPayload.meta) {
        meta.value = dataPayload.meta
      }

      return staffList.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$createStaff = async (payload) => {
    actionLoading.value = true
    try {
      const res = await s$staff.create(payload)
      const created = res?.data ?? res
      await a$fetchList()
      return created
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$updateStaff = async (id, payload) => {
    actionLoading.value = true
    try {
      const res = await s$staff.update(id, payload)
      const updated = res?.data ?? res
      await a$fetchList()
      return updated
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$deleteStaff = async (id) => {
    actionLoading.value = true
    try {
      const res = await s$staff.destroy(id)
      await a$fetchList()
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$toggleDuty = async (id) => {
    // Optimistic toggle in UI
    const target = staffList.value.find((s) => s.id === id)
    if (target) {
      target.is_on_duty = !target.is_on_duty
    }

    try {
      const res = await s$staff.toggleDuty(id)
      const updated = res?.data ?? res

      const idx = staffList.value.findIndex((s) => s.id === id)
      if (idx !== -1) {
        staffList.value[idx] = updated
      }

      // Refresh list & summary counts silently
      a$fetchList().catch(() => {})
      return updated
    } catch (e) {
      // Revert optimistic update on failure
      if (target) {
        target.is_on_duty = !target.is_on_duty
      }
      throw e?.message ?? e
    }
  }

  const a$fetchActiveAlerts = async () => {
    alertLoading.value = true
    try {
      const res = await s$staff.getActiveAlerts()
      const dataPayload = res?.data ?? res ?? {}

      activeAlerts.value = dataPayload.alerts || []
      activeAlertsCount.value = dataPayload.active_count || activeAlerts.value.length

      return activeAlerts.value
    } catch (e) {
      // Silently catch to avoid crashing header
      console.warn('Failed to fetch active alerts', e)
    } finally {
      alertLoading.value = false
    }
  }

  const a$acknowledgeAlert = async (id, acknowledgedBy = 'Admin') => {
    actionLoading.value = true
    try {
      const res = await s$staff.acknowledgeAlert(id, acknowledgedBy)
      await a$fetchActiveAlerts()
      return res?.data ?? res
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$setFilter = (key, value) => {
    filters.value[key] = value
    return a$fetchList()
  }

  // Real-time WebSocket Handler via Laravel Echo for Emergency Alerts
  let isListening = false

  const a$initWebSocket = () => {
    if (isListening || !echo) return

    echo.channel('alerts')
      .listen('.UrgentAlertTriggered', (payload) => {
        a$handleAlertBroadcast(payload)
      })
      .listen('UrgentAlertTriggered', (payload) => {
        a$handleAlertBroadcast(payload)
      })

    isListening = true
  }

  const a$handleAlertBroadcast = (payload) => {
    if (!payload) return
    // Refresh active alerts and staff list silently
    a$fetchActiveAlerts().catch(() => {})
    a$fetchList().catch(() => {})
  }

  const a$stopWebSocket = () => {
    if (!isListening || !echo) return
    echo.leaveChannel('alerts')
    isListening = false
  }

  return {
    // state
    staffList,
    summary,
    meta,
    activeAlerts,
    activeAlertsCount,
    loading,
    actionLoading,
    alertLoading,
    filters,
    // getters
    g$staffList,
    g$summary,
    g$meta,
    g$activeAlerts,
    g$activeAlertsCount,
    g$hasActiveAlerts,
    g$loading,
    g$actionLoading,
    g$filters,
    // actions
    a$fetchList,
    a$createStaff,
    a$updateStaff,
    a$deleteStaff,
    a$toggleDuty,
    a$fetchActiveAlerts,
    a$acknowledgeAlert,
    a$setFilter,
    a$initWebSocket,
    a$stopWebSocket,
  }
})

