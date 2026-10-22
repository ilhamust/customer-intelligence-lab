<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  isCollapsed: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle'])

const route = useRoute()
const isHovered = ref(false)
let hoverTimer = null

// Reset hover state if collapse state changes from toggle button
watch(
  () => props.isCollapsed,
  () => {
    if (hoverTimer) {
      clearTimeout(hoverTimer)
      hoverTimer = null
    }
    isHovered.value = false
  }
)

const isExpanded = computed(() => !props.isCollapsed || isHovered.value)

const handleMouseEnter = () => {
  if (props.isCollapsed) {
    if (hoverTimer) clearTimeout(hoverTimer)
    // Delay 2 detik sebelum melebar ke ukuran normal
    hoverTimer = setTimeout(() => {
      isHovered.value = true
    }, 2000)
  }
}

const handleMouseLeave = () => {
  if (hoverTimer) {
    clearTimeout(hoverTimer)
    hoverTimer = null
  }
  isHovered.value = false
}

const handleMenuClick = () => {
  // Batalkan timer hover tertunda
  if (hoverTimer) {
    clearTimeout(hoverTimer)
    hoverTimer = null
  }
  // Saat mode icon, klik menu tetap mempertahankan ukuran icon tanpa membesarkan sidebar
  if (props.isCollapsed) {
    isHovered.value = false
  }
}

onBeforeUnmount(() => {
  if (hoverTimer) {
    clearTimeout(hoverTimer)
    hoverTimer = null
  }
})

const menus = [
  {
    name: 'Dashboard',
    to: '/app/dashboard',
    disabled: true,
    icon: 'dashboard',
  },
  {
    name: 'Devices',
    to: '/app/devices',
    disabled: false,
    icon: 'smartphone',
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
    disabled: false,
    icon: 'users',
  },
  {
    name: 'Tickets',
    to: '/app/tickets',
    disabled: false,
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
  <!-- Placeholder to keep layout stable without shifting when sidebar floats/expands on hover -->
  <div v-if="isCollapsed" class="w-18 shrink-0 h-full pointer-events-none"></div>

  <!-- Main Sidebar Element -->
  <aside
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
    class="bg-base-100 border-r border-base-300 flex flex-col h-full select-none shrink-0 transition-all duration-300 ease-in-out"
    :class="[
      isCollapsed
        ? 'fixed left-0 top-0 bottom-0 z-50 ' + (isExpanded ? 'w-60 shadow-2xl' : 'w-18 shadow-xs')
        : 'relative z-30 w-60 shadow-xs'
    ]"
  >
    <!-- Brand Header -->
    <div
      class="h-16 border-b border-base-300 flex items-center shrink-0 transition-all duration-300"
      :class="[isExpanded ? 'justify-between px-5' : 'justify-center px-0']"
    >
      <router-link
        to="/app/conversations"
        @click="handleMenuClick"
        class="flex items-center gap-2.5 min-w-0 group"
        :title="!isExpanded ? 'Intelligence Lab' : undefined"
      >
        <!-- Brand Text (Hanya tampil saat expanded/hovered) -->
        <span
          v-if="isExpanded"
          class="text-[15px] font-black text-base-content tracking-tight truncate whitespace-nowrap"
        >
          Intelligence Lab
        </span>
      </router-link>

      <!-- Toggle Button (Tampil saat expanded) -->
      <button
        v-if="isExpanded"
        @click="$emit('toggle')"
        class="btn btn-ghost btn-xs btn-circle text-base-content/40 hover:text-base-content hover:bg-base-200"
        :title="isCollapsed ? 'Lock sidebar expanded' : 'Collapse sidebar'"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path v-if="isCollapsed" d="m9 18 6-6-6-6" />
          <path v-else d="m15 18-6-6 6-6" />
        </svg>
      </button>
    </div>

    <!-- Navigation Menus (Style Flat ala Referensi Consist) -->
    <nav class="flex-1 p-3 space-y-1.5 overflow-y-auto overflow-x-hidden">
      <!-- Section Title "MAIN MENU" -->
      <div v-if="isExpanded" class="px-2 pt-2 pb-1.5">
        <span class="text-[10px] font-extrabold text-base-content/40 uppercase tracking-wider whitespace-nowrap">
          Main Menu
        </span>
      </div>

      <template v-for="menu in menus" :key="menu.name">
        <!-- Active / Enabled Route Item: Solid Primary Capsule ala Consist -->
        <router-link
          v-if="!menu.disabled"
          :to="menu.to"
          @click="handleMenuClick"
          :title="!isExpanded ? menu.name : undefined"
          class="flex items-center rounded-xl text-xs transition-all duration-150 font-semibold"
          :class="[
            isExpanded ? 'gap-3 px-3.5 py-2.5 w-full' : 'justify-center w-11 h-11 mx-auto',
            isActive(menu.to)
              ? 'bg-primary text-primary-content shadow-xs'
              : 'text-base-content/60 hover:bg-base-200 hover:text-base-content'
          ]"
        >
          <!-- Chat icon -->
          <svg v-if="menu.icon === 'chat'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>

          <!-- Smartphone / Devices icon -->
          <svg v-else-if="menu.icon === 'smartphone'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="14" height="20" x="5" y="2" rx="2" ry="2" />
            <path d="M12 18h.01" />
          </svg>

          <!-- Users / Customers icon -->
          <svg v-else-if="menu.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>

          <!-- Dashboard icon -->
          <svg v-else-if="menu.icon === 'dashboard'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
          </svg>

          <!-- Ticket icon -->
          <svg v-else-if="menu.icon === 'ticket'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
            <path d="M13 5v2" /><path d="M13 17v2" /><path d="M13 11v2" />
          </svg>

          <!-- Bell icon -->
          <svg v-else-if="menu.icon === 'bell'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>

          <!-- Book icon -->
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
            <path d="M6 6h10" /><path d="M6 10h10" />
          </svg>

          <!-- Menu Label -->
          <span v-if="isExpanded" class="truncate tracking-tight whitespace-nowrap">{{ menu.name }}</span>
        </router-link>

        <!-- Disabled / Coming Soon Item -->
        <div
          v-else
          :title="`${menu.name} (Coming Soon)`"
          class="flex items-center rounded-xl text-xs text-base-content/30 cursor-not-allowed font-medium transition-colors"
          :class="[
            isExpanded ? 'gap-3 px-3.5 py-2.5 w-full' : 'justify-center w-11 h-11 mx-auto'
          ]"
        >
          <!-- Dashboard icon -->
          <svg v-if="menu.icon === 'dashboard'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <rect width="7" height="9" x="3" y="3" rx="1" />
            <rect width="7" height="5" x="14" y="3" rx="1" />
            <rect width="7" height="9" x="14" y="12" rx="1" />
            <rect width="7" height="5" x="3" y="16" rx="1" />
          </svg>
          <!-- Users icon -->
          <svg v-else-if="menu.icon === 'users'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          <!-- Ticket icon -->
          <svg v-else-if="menu.icon === 'ticket'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
            <path d="M13 5v2" /><path d="M13 17v2" /><path d="M13 11v2" />
          </svg>
          <!-- Bell icon -->
          <svg v-else-if="menu.icon === 'bell'" xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <!-- Book icon -->
          <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4.5 h-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z" />
            <path d="M6 6h10" /><path d="M6 10h10" />
          </svg>

          <!-- Menu Label -->
          <span v-if="isExpanded" class="truncate whitespace-nowrap">{{ menu.name }}</span>
        </div>
      </template>
    </nav>

    <!-- Bottom Promo / Status Card ala Referensi Consist -->
    <div v-if="isExpanded" class="p-3 shrink-0 animate-in fade-in duration-300 overflow-hidden">
      <div class="rounded-2xl bg-gradient-to-br from-primary to-secondary text-primary-content p-4 shadow-sm space-y-2">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
          <span class="text-[11px] font-bold uppercase tracking-wider text-primary-content/90">Gateway Online</span>
        </div>
        <p class="text-xs font-semibold leading-tight">Customer Intelligence AI Bot</p>
        <p class="text-[10px] text-primary-content/80 leading-normal">
          WhatsApp connected and ready to process customer inquiries.
        </p>
      </div>
    </div>

    <!-- Bottom Toggle Button (Saat Not Expanded) -->
    <div
      v-if="!isExpanded"
      class="p-2 border-t border-base-300 flex items-center justify-center shrink-0"
    >
      <button
        @click="$emit('toggle')"
        class="btn btn-ghost btn-xs btn-circle text-base-content/40 hover:text-base-content hover:bg-base-200"
        title="Expand sidebar"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>
  </aside>
</template>
