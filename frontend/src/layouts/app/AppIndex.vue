<script setup>
import { ref } from 'vue'
import AppSidebar from './AppSidebar.vue'
import AppNavbar from './AppNavbar.vue'

const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')

const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value
  localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value)
}
</script>

<template>
  <div class="h-screen w-screen overflow-hidden flex bg-base-200 text-base-content antialiased">
    <!-- Left Navigation Sidebar -->
    <AppSidebar :is-collapsed="isSidebarCollapsed" @toggle="toggleSidebar" />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
      <!-- Top Navigation Bar (h-14 sama dengan banner sidebar) -->
      <AppNavbar :is-sidebar-collapsed="isSidebarCollapsed" @toggle-sidebar="toggleSidebar" />

      <!-- Page Content View -->
      <main class="flex-1 overflow-hidden relative bg-base-200">
        <router-view />
      </main>
    </div>
  </div>
</template>
