<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useCustomerStore } from '@/stores/app/customer.store'
import { formatDateTime, formatPhone } from '@/utils/helper'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  customerId: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['close'])
const router = useRouter()
const p$customer = useCustomerStore()

const activeTab = ref('profile') // 'profile', 'points', 'conversations'

watch(
  () => props.open,
  async (val) => {
    if (val && props.customerId) {
      activeTab.value = 'profile'
      await p$customer.a$fetchDetail(props.customerId)
      await p$customer.a$fetchLedger(props.customerId)
    }
  }
)

const handleNavigateToChat = (conversationId) => {
  emit('close')
  router.push({ name: 'Conversations', query: { id: conversationId } })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-2xl w-full flex flex-col max-h-[90vh] overflow-hidden animate-in fade-in zoom-in-95 duration-150">
        <!-- Header -->
        <header class="p-5 border-b border-base-300 flex items-center justify-between shrink-0 bg-base-100">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-base-200 border border-base-300 flex items-center justify-center text-primary shrink-0 shadow-xs">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                <circle cx="12" cy="7" r="4" />
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="font-bold text-base text-base-content tracking-tight">
                  {{ p$customer.g$activeCustomer?.name || 'Customer Profile' }}
                </h3>
                <BaseBadge variant="neutral" pill size="xs">
                  {{ p$customer.g$activeCustomer?.customer_code || '-' }}
                </BaseBadge>
              </div>
              <p class="text-xs text-base-content/60">
                Customer 360° Profile & Loyalty Ledger
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content"
            title="Close dialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </header>

        <!-- Navigation Tabs -->
        <div class="flex items-center gap-2 px-5 pt-3 border-b border-base-300 bg-base-200/40 shrink-0">
          <button
            type="button"
            @click="activeTab = 'profile'"
            class="pb-2.5 px-3 text-xs font-bold transition-colors border-b-2"
            :class="[
              activeTab === 'profile'
                ? 'border-primary text-primary'
                : 'border-transparent text-base-content/60 hover:text-base-content'
            ]"
          >
            360° Overview
          </button>
          <button
            type="button"
            @click="activeTab = 'points'"
            class="pb-2.5 px-3 text-xs font-bold transition-colors border-b-2"
            :class="[
              activeTab === 'points'
                ? 'border-primary text-primary'
                : 'border-transparent text-base-content/60 hover:text-base-content'
            ]"
          >
            Jarvis Points
          </button>
          <button
            type="button"
            @click="activeTab = 'conversations'"
            class="pb-2.5 px-3 text-xs font-bold transition-colors border-b-2"
            :class="[
              activeTab === 'conversations'
                ? 'border-primary text-primary'
                : 'border-transparent text-base-content/60 hover:text-base-content'
            ]"
          >
            Chat History
          </button>
        </div>

        <!-- Body / Content -->
        <div class="flex-1 overflow-y-auto p-5">
          <div v-if="p$customer.g$detailLoading" class="py-12 flex justify-center">
            <BaseLoading text="Loading customer details..." />
          </div>

          <div v-else-if="p$customer.g$activeCustomer" class="space-y-4">
            <!-- TAB 1: 360 Overview -->
            <div v-if="activeTab === 'profile'" class="space-y-4">
              <!-- Grid Profile Info Cards -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/50 space-y-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">WhatsApp Phone</span>
                  <p class="text-xs font-semibold text-base-content select-all">
                    {{ formatPhone(p$customer.g$activeCustomer.phone) }}
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/50 space-y-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Driver Segment</span>
                  <div>
                    <BaseBadge
                      :variant="p$customer.g$activeCustomer.driver_type === 'ride-hailing' ? 'accent' : 'neutral'"
                      pill
                      size="xs"
                    >
                      {{ p$customer.g$activeCustomer.driver_type || 'General EV' }}
                    </BaseBadge>
                  </div>
                </div>

                <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/50 space-y-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">EV Vehicle</span>
                  <p class="text-xs font-semibold text-base-content">
                    {{ p$customer.g$activeCustomer.vehicle || 'Not specified' }}
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/50 space-y-1">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Residential Area</span>
                  <p class="text-xs font-semibold text-base-content">
                    {{ p$customer.g$activeCustomer.residential_area || 'Not specified' }}
                  </p>
                </div>

                <div class="p-3.5 rounded-xl border border-base-300 bg-base-200/50 space-y-1 sm:col-span-2">
                  <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Primary Route / Corridor</span>
                  <p class="text-xs font-semibold text-base-content">
                    {{ p$customer.g$activeCustomer.primary_route || 'Not specified' }}
                  </p>
                </div>
              </div>

              <!-- Registration Metadata -->
              <div class="p-4 rounded-xl border border-base-300 bg-base-100 flex items-center justify-between text-xs text-base-content/60">
                <span>Account Status: <strong class="text-base-content uppercase">{{ p$customer.g$activeCustomer.status }}</strong></span>
                <span>Registered: <strong class="text-base-content">{{ formatDateTime(p$customer.g$activeCustomer.created_at) }}</strong></span>
              </div>
            </div>

            <!-- TAB 2: Points & Ledger -->
            <div v-else-if="activeTab === 'points'" class="space-y-4">
              <!-- Points Hero Card -->
              <div class="p-4 rounded-2xl bg-gradient-to-br from-primary to-secondary text-primary-content flex items-center justify-between shadow-xs">
                <div class="space-y-1">
                  <p class="text-xs font-bold text-primary-content/80 uppercase tracking-wider">Available Balance</p>
                  <h4 class="text-3xl font-black">{{ p$customer.g$activeCustomer.points_balance }} Points</h4>
                  <p class="text-[11px] text-primary-content/70">Redeemable for EV charging discounts & snacks</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-primary-content shadow-xs">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="6" />
                    <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" />
                  </svg>
                </div>
              </div>

              <!-- Ledger History List -->
              <div class="space-y-2">
                <h5 class="text-xs font-bold text-base-content/70 uppercase tracking-wider">Transaction Ledger</h5>
                <div v-if="p$customer.g$activeLedger.length === 0" class="p-6 text-center text-xs text-base-content/50 border border-base-300 rounded-xl">
                  No points activity recorded yet.
                </div>
                <div v-else class="space-y-1.5">
                  <div
                    v-for="(item, idx) in p$customer.g$activeLedger"
                    :key="idx"
                    class="p-3 rounded-xl border border-base-300 bg-base-200/40 flex items-center justify-between text-xs"
                  >
                    <div>
                      <p class="font-semibold text-base-content">{{ item.description }}</p>
                      <p class="text-[10px] text-base-content/50">{{ formatDateTime(item.created_at) }}</p>
                    </div>
                    <span class="font-bold text-success">
                      +{{ item.points }} pts
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- TAB 3: Conversations -->
            <div v-else-if="activeTab === 'conversations'" class="space-y-3">
              <h5 class="text-xs font-bold text-base-content/70 uppercase tracking-wider">Linked WhatsApp Chats</h5>
              <div
                v-if="!p$customer.g$activeCustomer.conversations || p$customer.g$activeCustomer.conversations.length === 0"
                class="p-8 text-center text-xs text-base-content/50 border border-base-300 rounded-xl"
              >
                No WhatsApp conversations linked to this customer yet.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="conv in p$customer.g$activeCustomer.conversations"
                  :key="conv.id"
                  class="p-3.5 rounded-xl border border-base-300 bg-base-200/40 flex items-center justify-between hover:bg-base-200/80 transition-colors"
                >
                  <div class="min-w-0 pr-3">
                    <p class="font-bold text-xs text-base-content truncate">{{ conv.name || conv.phone }}</p>
                    <p class="text-[11px] text-base-content/60 truncate mt-0.5">{{ conv.last_message || 'No messages yet' }}</p>
                    <span class="text-[10px] text-base-content/40">{{ formatDateTime(conv.last_message_time || conv.updated_at) }}</span>
                  </div>

                  <BaseButton variant="primary" size="xs" @click="handleNavigateToChat(conv.id)">
                    Open Chat
                  </BaseButton>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <footer class="p-4 border-t border-base-300 flex justify-end shrink-0 bg-base-100">
          <BaseButton variant="outline" size="sm" @click="$emit('close')">
            Close
          </BaseButton>
        </footer>
      </div>
    </div>
  </Teleport>
</template>
