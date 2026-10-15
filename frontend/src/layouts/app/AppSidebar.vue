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
    badge: 'Live',
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
  <aside class="w-64 bg-base-100 border-r border-base-300 flex flex-col h-full select-none shrink-0 z-30 transition-all duration-200">
    <!-- Brand Header -->
    <div class="h-16 px-5 flex items-center gap-3 border-b border-base-300">
      <div class="w-9 h-9 rounded-lg bg-primary text-primary-content flex items-center justify-center font-bold text-base shadow-xs shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
        </svg>
      </div>
      <div class="min-w-0">
        <h1 class="font-bold text-sm text-base-content tracking-tight truncate leading-tight">Customer Intelligence</h1>
        <p class="text-[11px] text-base-content/50 font-medium tracking-wide">Back Office Portal</p>
      </div>
    </div>

    <!-- Gateway Status -->
    <div class="px-4 py-3 border-b border-base-300/60 bg-base-200/50">
      <div class="flex items-center justify-between text-xs">
        <div class="flex items-center gap-2 text-base-content/70 font-medium">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-success"></span>
          </span>
          <span class="text-[11px] text-base-content/80 font-mono">WA Gateway</span>
        </div>
        <span class="text-[10px] uppercase font-bold tracking-wider text-success px-1.5 py-0.5 rounded bg-success/10">
          Connected
        </span>
      </div>
    </div>

    <!-- Navigation Menus -->
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
      <div class="text-[10px] uppercase font-bold tracking-wider text-base-content/40 px-3 pb-1">
        Menu Utama
      </div>

      <template v-for="menu in menus" :key="menu.name">
        <router-link
          v-if="!menu.disabled"
          :to="menu.to"
          class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="[
            isActive(menu.to)
              ? 'bg-primary/10 text-primary font-semibold'
              : 'text-base-content/70 hover:bg-base-200 hover:text-base-content'
          ]"
        >
          <div class="flex items-center gap-3 min-w-0">
            <!-- Chat icon -->
            <svg v-if="menu.icon === 'chat'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
            </svg>
            <span class="truncate text-xs">{{ menu.name }}</span>
          </div>

          <span v-if="menu.badge" class="badge badge-xs bg-primary text-primary-content font-bold px-1.5 py-0.5">
            {{ menu.badge }}
          </span>
        </router-link>

        <div
          v-else
          class="flex items-center justify-between px-3 py-2.5 rounded-lg text-sm text-base-content/30 cursor-not-allowed"
          :title="`${menu.name} (Segera Hadir)`"
        >
          <div class="flex items-center gap-3 min-w-0">
            <!-- Dashboard icon -->
            <svg v-if="menu.icon === 'dashboard'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="7" height="9" x="3" y="3" rx="1" />
              <rect width="7" height="5" x="14" y="3" rx="1" />
              <rect width="7" height="9" x="14" y="12" rx="1" />
              <rect width="7" height="5" x="3" y="16" rx="1" />
            </svg>
            <!-- Users icon -->
            <svg v-else-if="menu.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
            <!-- Ticket icon -->
            <svg v-else-if="menu.icon === 'ticket'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
              <path d="M13 5v2" /><path d="M13 17v2" /><path d="M13 11v2" />
            </svg>
            <!-- Bell icon -->
            <svg v-else-if="menu.icon === 'bell'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
              <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
            </svg>
            <!-- Book icon -->
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
              <path d="M6 6h10" /><path d="M6 10h10" />
            </svg>
            <span class="truncate text-xs">{{ menu.name }}</span>
          </div>
        </div>
      </template>
    </nav>

    <!-- Sidebar Footer -->
    <div class="p-3 border-t border-base-300 text-[11px] text-base-content/40 text-center">
      v1.0.0 &bull; CIL Back Office
    </div>
  </aside>
</template>
