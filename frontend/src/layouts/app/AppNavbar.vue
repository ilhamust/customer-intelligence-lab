<script setup>
import { ref, onMounted } from 'vue'

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
    <!-- Breadcrumb & Title -->
    <div class="flex items-center gap-3">
      <div class="text-xs text-base-content/50 font-medium flex items-center gap-1.5">
        <span>Back Office</span>
        <span>/</span>
        <span class="text-base-content font-semibold text-sm">Conversations</span>
      </div>
    </div>

    <!-- Right Controls -->
    <div class="flex items-center gap-4">
      <!-- AI Status Badge -->
      <div class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-base-200 border border-base-300 text-xs text-base-content/70">
        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
        <span class="font-medium text-[11px]">AI Copilot: Siap Melayani</span>
      </div>

      <!-- Theme Switcher -->
      <button
        @click="toggleTheme"
        class="btn btn-ghost btn-circle btn-sm text-base-content/70 hover:text-base-content"
        :title="currentTheme === 'light' ? 'Beralih ke Mode Gelap' : 'Beralih ke Mode Terang'"
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

      <!-- Admin Profile Pill -->
      <div class="flex items-center gap-2.5 pl-3 border-l border-base-300">
        <div class="w-8 h-8 rounded-full bg-neutral text-neutral-content font-bold text-xs flex items-center justify-center">
          AD
        </div>
        <div class="hidden md:block leading-tight text-left">
          <p class="text-xs font-semibold text-base-content leading-none">Admin</p>
          <span class="text-[10px] text-base-content/50 font-medium">Administrator</span>
        </div>
      </div>
    </div>
  </header>
</template>
