<script setup>
import AppSidebar from './AppSidebar.vue'
import AppNavbar from './AppNavbar.vue'
</script>

<template>
  <div class="h-screen w-screen overflow-hidden flex bg-base-200 text-base-content antialiased">
    <!-- Left Navigation Sidebar -->
    <AppSidebar />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
      <!-- Top Navigation Bar -->
      <AppNavbar />

      <!-- Page Content View -->
      <main class="flex-1 overflow-hidden relative bg-base-200">
        <router-view />
      </main>
    </div>
  </div>
</template>
