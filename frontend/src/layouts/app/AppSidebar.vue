<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const menus = [
  {
    name: 'Dashboard',
    to: '/app/dashboard',
    disabled: true,
    icon: 'dashboard',
  },
  {
    name: 'Conversations',
    to: '/app/conversations',
    disabled: false,
    icon: 'chat',
  },
  {
    name: 'Customers',
    to: '/app/customers',
    disabled: true,
    icon: 'users',
  },
  {
    name: 'Tickets',
    to: '/app/tickets',
    disabled: true,
    icon: 'ticket',
  },
  {
    name: 'Alerts',
    to: '/app/alerts',
    disabled: true,
    icon: 'bell',
  },
  {
    name: 'Knowledge',
    to: '/app/knowledge',
    disabled: true,
    icon: 'book',
  },
]

const isActive = (to) => computed(() => route.path.startsWith(to)).value
</script>

<template>
  <aside class="w-56 bg-base-100 border-r border-base-300/40 flex flex-col h-full select-none shrink-0 z-30 transition-all duration-200">
    <!-- Brand Header -->
    <div class="h-14 px-5 border-b border-base-300/40 flex items-center justify-between">
      <router-link to="/app/conversations" class="flex items-center space-x-3 group">
        <span class="text-lg font-black text-base-content tracking-tight">
          Intelligence Lab
        </span>
      </router-link>
    </div>

    <!-- Navigation Menus -->
    <nav class="flex-1 p-3 space-y-1.5 overflow-y-auto">
      <template v-for="menu in menus" :key="menu.name">
        <router-link
          v-if="!menu.disabled"
          :to="menu.to"
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs transition-colors duration-150"
          :class="[
            isActive(menu.to)
              ? 'bg-primary text-primary-content font-semibold shadow-xs'
              : 'text-base-content/70 hover:bg-base-200/60 hover:text-base-content font-medium'
          ]"
        >
          <!-- Chat icon -->
          <svg v-if="menu.icon === 'chat'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
          <span class="truncate">{{ menu.name }}</span>
        </router-link>

        <div
          v-else
          class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs text-base-content/30 cursor-not-allowed font-normal"
          :title="`${menu.name} (Segera Hadir)`"
        >
          <!-- Dashboard icon -->
          <svg v-if="menu.icon === 'dashboard'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
          </svg>
          <!-- Users icon -->
          <svg v-else-if="menu.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          <!-- Ticket icon -->
          <svg v-else-if="menu.icon === 'ticket'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
            <path d="M13 5v2" /><path d="M13 17v2" /><path d="M13 11v2" />
          </svg>
          <!-- Bell icon -->
          <svg v-else-if="menu.icon === 'bell'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <!-- Book icon -->
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
            <path d="M6 6h10" /><path d="M6 10h10" />
          </svg>
          <span class="truncate">{{ menu.name }}</span>
        </div>
      </template>
    </nav>
  </aside>
</template>
