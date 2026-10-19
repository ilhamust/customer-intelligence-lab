<script setup>
import { onMounted, ref } from 'vue'
import { useCustomerStore } from '@/stores/app/customer.store'
import { formatPhone } from '@/utils/helper'
import { notify } from '@/plugins'
import CustomerDetailModal from './components/CustomerDetailModal.vue'

const p$customer = useCustomerStore()
const searchInput = ref('')

const showDetailModal = ref(false)
const selectedCustomerId = ref(null)

const columns = [
  { key: 'customer_code', label: 'ID', class: 'w-28' },
  { key: 'name', label: 'Customer' },
  { key: 'vehicle', label: 'Vehicle' },
  { key: 'driver_type', label: 'Segment', class: 'w-32 text-center' },
  { key: 'residential_area', label: 'Area / Route' },
  { key: 'points_balance', label: 'Points', class: 'w-28 text-right' },
  { key: 'status', label: 'Status', class: 'w-24 text-center' },
  { key: 'actions', label: 'Action', class: 'w-28 text-right' },
]

onMounted(async () => {
  await fetchCustomers()
})

const fetchCustomers = async () => {
  try {
    await p$customer.a$fetchList()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to load customers')
  }
}

const handleSearch = (e) => {
  searchInput.value = e.target.value
  p$customer.a$setFilter('search', e.target.value)
}

const handleFilterDriver = (type) => {
  p$customer.a$setFilter('driver_type', type)
}

const handleFilterStatus = (status) => {
  p$customer.a$setFilter('status', status)
}

const handleOpenDetail = (customer) => {
  selectedCustomerId.value = customer.id
  showDetailModal.value = true
}

const handlePageChange = (page) => {
  p$customer.a$setPage(page)
}
</script>

<template>
  <div class="h-full p-4 flex flex-col gap-4 bg-base-200 overflow-y-auto select-none">
    <!-- ─── Header Floating Card ───────────────────────────────── -->
    <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5 shrink-0">
      <div class="space-y-0.5">
        <div class="flex items-center gap-2.5">
          <h1 class="text-lg font-black text-base-content tracking-tight">Customers</h1>
          <BaseBadge v-if="p$customer.g$summary.total_customers > 0" variant="neutral" pill size="sm">
            {{ p$customer.g$summary.total_customers }} Registered
          </BaseBadge>
        </div>
        <p class="text-xs text-base-content/60">360° customer intelligence profiles & loyalty points</p>
      </div>

      <!-- Action & Search Bar -->
      <div class="flex flex-wrap items-center gap-2.5">
        <!-- Search Input Pill -->
        <div class="relative w-full sm:w-64">
          <input
            :value="searchInput"
            @input="handleSearch"
            type="text"
            placeholder="Search name, phone, EV..."
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
          @click="fetchCustomers"
          :disabled="p$customer.g$loading"
          class="btn btn-sm btn-ghost btn-circle text-base-content/60 hover:text-base-content"
          title="Refresh customer list"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" :class="{ 'animate-spin': p$customer.g$loading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
            <path d="M3 3v5h5" />
            <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
            <path d="M16 21h5v-5" />
          </svg>
        </button>
      </div>
    </header>

    <!-- ─── Metric KPI Cards ───────────────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 shrink-0">
      <BaseStat
        label="Total Customers"
        :value="p$customer.g$summary.total_customers"
        subtext="Registered in Back Office"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Active Customers"
        :value="p$customer.g$summary.active_customers"
        subtext="Active accounts"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
            <polyline points="22 4 12 14.01 9 11.01" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Jarvis Points"
        :value="p$customer.g$summary.total_points"
        subtext="Loyalty points in circulation"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="6" />
            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
          </svg>
        </template>
      </BaseStat>

      <BaseStat
        label="Ride-Hailing Drivers"
        :value="p$customer.g$summary.ride_hailing_count"
        subtext="Ojol & Taxi EV segment"
      >
        <template #icon>
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2" />
            <circle cx="7" cy="17" r="2" />
            <path d="M9 17h6" />
            <circle cx="17" cy="17" r="2" />
          </svg>
        </template>
      </BaseStat>
    </div>

    <!-- ─── Filter Pills Bar ───────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-base-100 rounded-2xl border border-base-300 p-3 shadow-xs shrink-0 text-xs">
      <div class="flex items-center gap-1.5 overflow-x-auto">
        <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/40 px-2">Segment:</span>
        <button
          type="button"
          @click="handleFilterDriver('all')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.driver_type === 'all' ? 'bg-primary text-primary-content shadow-xs' : 'text-base-content/60 hover:bg-base-200'"
        >
          All
        </button>
        <button
          type="button"
          @click="handleFilterDriver('ride-hailing')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.driver_type === 'ride-hailing' ? 'bg-primary text-primary-content shadow-xs' : 'text-base-content/60 hover:bg-base-200'"
        >
          Ride-Hailing
        </button>
        <button
          type="button"
          @click="handleFilterDriver('private')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.driver_type === 'private' ? 'bg-primary text-primary-content shadow-xs' : 'text-base-content/60 hover:bg-base-200'"
        >
          Private
        </button>
        <button
          type="button"
          @click="handleFilterDriver('fleet')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.driver_type === 'fleet' ? 'bg-primary text-primary-content shadow-xs' : 'text-base-content/60 hover:bg-base-200'"
        >
          Fleet
        </button>
      </div>

      <!-- Status Filters -->
      <div class="flex items-center gap-1.5">
        <span class="text-[11px] font-bold uppercase tracking-wider text-base-content/40 px-2">Status:</span>
        <button
          type="button"
          @click="handleFilterStatus('all')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.status === 'all' ? 'bg-neutral text-neutral-content' : 'text-base-content/60 hover:bg-base-200'"
        >
          All
        </button>
        <button
          type="button"
          @click="handleFilterStatus('active')"
          class="px-3 py-1 rounded-full font-semibold transition-colors"
          :class="p$customer.g$filters.status === 'active' ? 'bg-neutral text-neutral-content' : 'text-base-content/60 hover:bg-base-200'"
        >
          Active
        </button>
      </div>
    </div>

    <!-- ─── Main Customer Table ────────────────────────────────── -->
    <main class="flex-1 flex flex-col gap-3">
      <BaseTable
        :columns="columns"
        :rows="p$customer.g$customers"
        :loading="p$customer.g$loading"
        empty-title="No customers found"
        empty-description="Customers who register via WhatsApp will appear here with 360° profile details."
      >
        <!-- Cell: Customer Code -->
        <template #cell-customer_code="{ row }">
          <BaseBadge variant="neutral" pill size="sm" class="font-bold">
            {{ row.customer_code }}
          </BaseBadge>
        </template>

        <!-- Cell: Customer Name & Phone -->
        <template #cell-name="{ row }">
          <div class="min-w-0 space-y-0.5">
            <p class="font-bold text-base-content tracking-tight">{{ row.name }}</p>
            <p class="text-[11px] text-base-content/60">{{ formatPhone(row.phone) }}</p>
          </div>
        </template>

        <!-- Cell: Vehicle -->
        <template #cell-vehicle="{ row }">
          <span class="font-medium text-base-content">
            {{ row.vehicle || '-' }}
          </span>
        </template>

        <!-- Cell: Driver Segment -->
        <template #cell-driver_type="{ row }">
          <div class="flex justify-center">
            <BaseBadge
              :variant="row.driver_type === 'ride-hailing' ? 'accent' : 'neutral'"
              pill
              size="xs"
              class="capitalize font-semibold"
            >
              {{ row.driver_type || 'General' }}
            </BaseBadge>
          </div>
        </template>

        <!-- Cell: Residential Area & Route -->
        <template #cell-residential_area="{ row }">
          <div class="space-y-0.5">
            <p class="text-xs text-base-content font-medium">{{ row.residential_area || '-' }}</p>
            <p v-if="row.primary_route" class="text-[10px] text-base-content/50 truncate max-w-xs">
              {{ row.primary_route }}
            </p>
          </div>
        </template>

        <!-- Cell: Points -->
        <template #cell-points_balance="{ row }">
          <div class="font-extrabold text-xs text-base-content text-right">
            {{ row.points_balance }} <span class="text-[10px] text-base-content/50 font-normal">pts</span>
          </div>
        </template>

        <!-- Cell: Status -->
        <template #cell-status="{ row }">
          <div class="flex justify-center">
            <BaseBadge
              :variant="row.status === 'active' ? 'neutral' : 'neutral'"
              pill
              size="xs"
              class="gap-1 font-semibold"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="row.status === 'active' ? 'bg-emerald-500' : 'bg-base-content/30'"></span>
              <span class="capitalize">{{ row.status }}</span>
            </BaseBadge>
          </div>
        </template>

        <!-- Cell: Action Button -->
        <template #cell-actions="{ row }">
          <div class="flex justify-end">
            <BaseButton
              variant="outline"
              size="xs"
              @click="handleOpenDetail(row)"
            >
              360° Profile
            </BaseButton>
          </div>
        </template>
      </BaseTable>

      <!-- Pagination -->
      <div v-if="p$customer.g$meta.total > 0" class="bg-base-100 rounded-2xl border border-base-300 shadow-xs">
        <BasePagination
          :meta="p$customer.g$meta"
          @change="handlePageChange"
        />
      </div>
    </main>

    <!-- Customer 360° Detail Modal -->
    <CustomerDetailModal
      :open="showDetailModal"
      :customer-id="selectedCustomerId"
      @close="showDetailModal = false"
    />
  </div>
</template>
