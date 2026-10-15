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
  <header class="h-13 bg-base-100 border-b border-base-300/40 px-5 flex items-center justify-between shrink-0 select-none z-20">
    <!-- Breadcrumb Simpel ala Gambar 2 (> Conversations) -->
    <div class="flex items-center gap-2 text-xs font-medium text-base-content/80">
      <span class="text-base-content/40 font-mono text-sm">&gt;</span>
      <span class="font-semibold text-xs tracking-wide text-base-content">Conversations</span>
    </div>

    <!-- Right Controls Ringkas -->
    <div class="flex items-center gap-3">
      <!-- Theme Switcher -->
      <button
        @click="toggleTheme"
        class="btn btn-ghost btn-circle btn-xs text-base-content/50 hover:text-base-content"
        :title="currentTheme === 'light' ? 'Beralih ke Mode Gelap' : 'Beralih ke Mode Terang'"
      >
        <!-- Sun icon -->
        <svg v-if="currentTheme === 'dark'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="4" />
          <path d="M12 2v2" /><path d="M12 20v2" />
          <path d="m4.93 4.93 1.41 1.41" /><path d="m17.66 17.66 1.41 1.41" />
          <path d="M2 12h2" /><path d="M20 12h2" />
          <path d="m6.34 17.66-1.41 1.41" /><path d="m19.07 4.93-1.41 1.41" />
        </svg>
        <!-- Moon icon -->
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
        </svg>
      </button>

      <!-- Minimal Admin Avatar ala Gambar 2 -->
      <div class="w-7 h-7 rounded-full bg-base-200 border border-base-300 text-base-content font-bold text-xs flex items-center justify-center cursor-default" title="Super Admin">
        A
      </div>
    </div>
  </header>
</template>
