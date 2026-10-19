// src/stores/app/customer.store.js
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as s$customer from '@/services/app/customer.service'

export const useCustomerStore = defineStore('customer', () => {
  // ─── State ───────────────────────────────────────────
  const loading = ref(false)
  const detailLoading = ref(false)
  const customers = ref([])
  const activeCustomer = ref(null)
  const activeLedger = ref([])
  const meta = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  })
  const summary = ref({
    total_customers: 0,
    active_customers: 0,
    total_points: 0,
    ride_hailing_count: 0,
    private_count: 0,
  })
  const filters = ref({
    search: '',
    driver_type: 'all',
    status: 'all',
    page: 1,
  })

  // ─── Getters ─────────────────────────────────────────
  const g$loading = computed(() => loading.value)
  const g$detailLoading = computed(() => detailLoading.value)
  const g$customers = computed(() => customers.value)
  const g$activeCustomer = computed(() => activeCustomer.value)
  const g$activeLedger = computed(() => activeLedger.value)
  const g$meta = computed(() => meta.value)
  const g$summary = computed(() => summary.value)
  const g$filters = computed(() => filters.value)

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async (customParams = {}) => {
    loading.value = true
    try {
      const params = {
        search: filters.value.search,
        driver_type: filters.value.driver_type,
        status: filters.value.status,
        page: filters.value.page,
        ...customParams,
      }

      const res = await s$customer.list(params)
      const dataPayload = res?.data ?? res ?? {}

      customers.value = dataPayload.data || []
      if (dataPayload.meta) {
        meta.value = dataPayload.meta
      }
      if (dataPayload.summary) {
        summary.value = dataPayload.summary
      }

      return customers.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$fetchDetail = async (id) => {
    detailLoading.value = true
    try {
      const res = await s$customer.show(id)
      activeCustomer.value = res?.data ?? res ?? null
      return activeCustomer.value
    } catch (e) {
      throw e?.message ?? e
    } finally {
      detailLoading.value = false
    }
  }

  const a$fetchLedger = async (id) => {
    try {
      const res = await s$customer.ledger(id)
      const dataPayload = res?.data ?? res ?? {}
      activeLedger.value = dataPayload.ledger || []
      return activeLedger.value
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$setFilter = (key, value) => {
    filters.value[key] = value
    filters.value.page = 1
    return a$fetchList()
  }

  const a$setPage = (page) => {
    filters.value.page = page
    return a$fetchList()
  }

  const a$resetFilter = () => {
    filters.value = {
      search: '',
      driver_type: 'all',
      status: 'all',
      page: 1,
    }
    return a$fetchList()
  }

  return {
    g$loading,
    g$detailLoading,
    g$customers,
    g$activeCustomer,
    g$activeLedger,
    g$meta,
    g$summary,
    g$filters,
    a$fetchList,
    a$fetchDetail,
    a$fetchLedger,
    a$setFilter,
    a$setPage,
    a$resetFilter,
  }
})
