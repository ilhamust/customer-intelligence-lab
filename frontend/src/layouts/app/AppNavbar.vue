<script setup>
import { ref, onMounted } from 'vue'

defineProps({
  isSidebarCollapsed: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['toggle-sidebar'])

const currentTheme = ref('light')

const toggleTheme = () => {
  currentTheme.value = currentTheme.value === 'light' ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', currentTheme.value)
}

onMounted(() => {
  const initialTheme = document.documentElement.getAttribute('data-theme') || 'light'
  currentTheme.value = initialTheme
})
</script>

<template>
  <header class="h-16 bg-base-100 border-b border-base-300 px-6 flex items-center justify-between shrink-0 select-none z-20">
    <!-- Left Section: Spacer -->
    <div></div>

    <!-- Right Controls -->
    <div class="flex items-center gap-3">
      <!-- Theme Switcher -->
      <button
        @click="toggleTheme"
        class="btn btn-ghost btn-circle btn-sm text-base-content/60 hover:text-base-content hover:bg-base-200"
        :title="currentTheme === 'light' ? 'Switch to Dark Mode' : 'Switch to Light Mode'"
      >
        <!-- Sun icon -->
        <svg v-if="currentTheme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="4" />
          <path d="M12 2v2" /><path d="M12 20v2" />
          <path d="m4.93 4.93 1.41 1.41" /><path d="m17.66 17.66 1.41 1.41" />
          <path d="M2 12h2" /><path d="M20 12h2" />
          <path d="m6.34 17.66-1.41 1.41" /><path d="m19.07 4.93-1.41 1.41" />
        </svg>
        <!-- Moon icon -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
        </svg>
      </button>

      <div class="h-6 w-px bg-base-300 mx-1"></div>

      <!-- User Profile Pill ala Referensi Consist -->
      <div class="flex items-center gap-2.5 pl-1 select-none">
        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-accent to-warning text-accent-content font-bold text-xs flex items-center justify-center shadow-xs">
          AD
        </div>
        <div class="hidden sm:block text-left leading-none">
          <p class="text-xs font-bold text-base-content">Admin</p>
          <span class="text-[10px] text-base-content/40 font-medium">Supervisor</span>
        </div>
      </div>
    </div>
  </header>
</template>
