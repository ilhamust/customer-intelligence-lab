// src/stores/app/charging.store.js
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as s$charging from '@/services/app/charging.service'
import { echo } from '@/plugins/echo'

export const useChargingStore = defineStore('charging', () => {
  // ─── State ───────────────────────────────────────────
  const transactions = ref([])
  const summary = ref({
    total_transactions: 0,
    total_kwh: 0,
    total_revenue: 0,
    total_points_awarded: 0,
    member_transactions: 0,
    non_member_transactions: 0,
  })
  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 1,
    to: 15,
  })
  const loading = ref(false)
  const selectedTransaction = ref(null)

  const filters = ref({
    search: '',
    station_code: 'all',
    status: 'all',
    date_from: null,
    date_to: null,
    page: 1,
    per_page: 15,
  })

  // ─── Getters ─────────────────────────────────────────
  const g$transactions = computed(() => transactions.value)
  const g$summary = computed(() => summary.value)
  const g$meta = computed(() => meta.value)
  const g$loading = computed(() => loading.value)
  const g$filters = computed(() => filters.value)
  const g$selectedTransaction = computed(() => selectedTransaction.value)

  // ─── Actions ─────────────────────────────────────────
  const a$fetchTransactions = async (customParams = {}) => {
    loading.value = true
    try {
      const params = {
        search: filters.value.search || undefined,
        station_code: filters.value.station_code !== 'all' ? filters.value.station_code : undefined,
        status: filters.value.status !== 'all' ? filters.value.status : undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        page: filters.value.page,
        per_page: filters.value.per_page,
        ...customParams,
      }

      const res = await s$charging.listTransactions(params)
      const dataPayload = res?.data ?? res ?? {}

      transactions.value = dataPayload.data || []
      if (dataPayload.summary) {
        summary.value = dataPayload.summary
      }
      if (dataPayload.meta) {
        meta.value = dataPayload.meta
      }

      return transactions.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$setFilter = (key, value) => {
    filters.value[key] = value
    filters.value.page = 1
    return a$fetchTransactions()
  }

  const a$setPage = (page) => {
    filters.value.page = page
    return a$fetchTransactions()
  }

  const a$selectTransaction = (trx) => {
    selectedTransaction.value = trx
  }

  // ─── Real-time WebSocket Handler via Laravel Echo Reverb ──────────
  let isListening = false

  const a$initWebSocket = () => {
    if (isListening || !echo) return

    echo.channel('charging')
      .listen('.ChargingTransactionCompleted', () => {
        a$fetchTransactions().catch(() => {})
      })
      .listen('ChargingTransactionCompleted', () => {
        a$fetchTransactions().catch(() => {})
      })

    isListening = true
  }

  const a$stopWebSocket = () => {
    if (!isListening || !echo) return
    echo.leaveChannel('charging')
    isListening = false
  }

  return {
    // state
    transactions,
    summary,
    meta,
    loading,
    selectedTransaction,
    filters,
    // getters
    g$transactions,
    g$summary,
    g$meta,
    g$loading,
    g$filters,
    g$selectedTransaction,
    // actions
    a$fetchTransactions,
    a$setFilter,
    a$setPage,
    a$selectTransaction,
    a$initWebSocket,
    a$stopWebSocket,
  }
})

