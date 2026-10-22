// src/stores/app/ticket.store.js
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as s$ticket from '@/services/app/ticket.service'
import { echo } from '@/plugins/echo'

export const useTicketStore = defineStore('ticket', () => {
  // ─── State ───────────────────────────────────────────
  const tickets = ref([])
  const summary = ref({
    total: 0,
    queued: 0,
    in_progress: 0,
    resolved: 0,
  })
  const csStatus = ref({
    is_busy: false,
    active_ticket: null,
    queued_count: 0,
    in_progress_count: 0,
    resolved_today_count: 0,
  })
  const activeTab = ref('queued') // 'queued' | 'in_progress' | 'resolved' | 'all'
  const loading = ref(false)
  const actionLoading = ref(false)
  const statusLoading = ref(false)
  const filters = ref({
    search: '',
    category: 'all',
    page: 1,
    per_page: 50,
  })

  // ─── Getters ─────────────────────────────────────────
  const g$tickets = computed(() => tickets.value)
  const g$summary = computed(() => summary.value)
  const g$csStatus = computed(() => csStatus.value)
  const g$isBusy = computed(() => csStatus.value.is_busy)
  const g$activeTicket = computed(() => csStatus.value.active_ticket)
  const g$loading = computed(() => loading.value)
  const g$actionLoading = computed(() => actionLoading.value)
  const g$activeTab = computed(() => activeTab.value)
  const g$filters = computed(() => filters.value)

  const g$queuedTickets = computed(() =>
    tickets.value.filter((t) => t.status === 'queued')
  )

  const g$inProgressTickets = computed(() =>
    tickets.value.filter((t) => t.status === 'in_progress')
  )

  const g$resolvedTickets = computed(() =>
    tickets.value.filter((t) => t.status === 'resolved')
  )

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async (customParams = {}) => {
    loading.value = true
    try {
      const params = {
        search: filters.value.search || undefined,
        category: filters.value.category !== 'all' ? filters.value.category : undefined,
        status: activeTab.value !== 'all' ? activeTab.value : undefined,
        page: filters.value.page,
        per_page: filters.value.per_page,
        ...customParams,
      }

      const res = await s$ticket.list(params)
      const dataPayload = res?.data ?? res ?? {}

      tickets.value = dataPayload.data || []
      if (dataPayload.summary) {
        summary.value = dataPayload.summary
      }

      return tickets.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$fetchStatus = async () => {
    statusLoading.value = true
    try {
      const res = await s$ticket.getCsStatus()
      const dataPayload = res?.data ?? res ?? {}
      csStatus.value = dataPayload

      return csStatus.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      statusLoading.value = false
    }
  }

  const a$serveTicket = async (id, adminName = null) => {
    actionLoading.value = true
    try {
      const res = await s$ticket.serve(id, adminName)
      const updatedTicket = res?.data ?? res

      // Update tiket di lokal state
      const idx = tickets.value.findIndex((t) => t.id === id)
      if (idx !== -1) {
        tickets.value[idx] = updatedTicket
      }

      // Refresh CS status & list
      await a$fetchStatus()
      await a$fetchList()

      return updatedTicket
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$resolveTicket = async (id, resolutionNotes = null) => {
    actionLoading.value = true
    try {
      const res = await s$ticket.resolve(id, resolutionNotes)
      const resolvedTicket = res?.data ?? res

      // Update tiket di lokal state
      const idx = tickets.value.findIndex((t) => t.id === id)
      if (idx !== -1) {
        tickets.value[idx] = resolvedTicket
      }

      // Refresh CS status & list
      await a$fetchStatus()
      await a$fetchList()

      return resolvedTicket
    } catch (e) {
      throw e?.message ?? e
    } finally {
      actionLoading.value = false
    }
  }

  const a$setTab = (tab) => {
    activeTab.value = tab
    filters.value.page = 1
    return a$fetchList()
  }

  const a$setFilter = (key, value) => {
    filters.value[key] = value
    filters.value.page = 1
    return a$fetchList()
  }

  // Real-time WebSocket Handler via Laravel Echo
  let isListening = false

  const a$initWebSocket = () => {
    if (isListening || !echo) return

    echo.channel('tickets')
      .listen('.TicketQueueUpdated', (payload) => {
        a$handleBroadcast(payload)
      })
      .listen('TicketQueueUpdated', (payload) => {
        a$handleBroadcast(payload)
      })

    isListening = true
  }

  const a$handleBroadcast = (payload) => {
    if (!payload) return

    if (payload.summary) {
      summary.value = payload.summary
    }

    // Refresh status and list silently in the background
    a$fetchStatus().catch(() => {})
    a$fetchList().catch(() => {})
  }

  const a$stopWebSocket = () => {
    if (!isListening || !echo) return
    echo.leaveChannel('tickets')
    isListening = false
  }

  return {
    // state
    tickets,
    summary,
    csStatus,
    activeTab,
    loading,
    actionLoading,
    statusLoading,
    filters,
    // getters
    g$tickets,
    g$summary,
    g$csStatus,
    g$isBusy,
    g$activeTicket,
    g$loading,
    g$actionLoading,
    g$activeTab,
    g$filters,
    g$queuedTickets,
    g$inProgressTickets,
    g$resolvedTickets,
    // actions
    a$fetchList,
    a$fetchStatus,
    a$serveTicket,
    a$resolveTicket,
    a$setTab,
    a$setFilter,
    a$initWebSocket,
    a$stopWebSocket,
  }
})
