<script setup>
import { ref, onMounted } from 'vue'

const currentTheme = ref('light')
const testInput = ref('')
const counter = ref(0)

const toggleTheme = () => {
  currentTheme.value = currentTheme.value === 'light' ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', currentTheme.value)
}

onMounted(() => {
  const initialTheme = document.documentElement.getAttribute('data-theme') || 'light'
  currentTheme.value = initialTheme
  document.documentElement.setAttribute('data-theme', currentTheme.value)
})
</script>

<template>
  <div class="min-h-screen bg-base-200 text-base-content transition-colors duration-300">
    <!-- Navbar -->
    <header class="navbar bg-base-100 shadow-sm border-b border-base-300 px-4 lg:px-12 sticky top-0 z-50">
      <div class="flex-1 items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-primary text-primary-content font-black flex items-center justify-center text-lg shadow-sm">
          C
        </div>
        <div>
          <span class="font-bold text-lg tracking-tight">Customer Intelligence Lab</span>
          <span class="badge badge-accent badge-sm ml-2 font-medium">Palette Test</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button 
          @click="toggleTheme" 
          class="btn btn-sm btn-outline border-base-300 hover:border-primary flex items-center gap-2"
          id="btn-toggle-theme"
        >
          <span v-if="currentTheme === 'light'">🌙 Mode Gelap</span>
          <span v-else>☀️ Mode Terang</span>
        </button>
      </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-10 space-y-12">
      <!-- Hero Section -->
      <section class="text-center space-y-5 py-6">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-base-100 border border-base-300 text-xs font-semibold shadow-xs">
          <span class="w-2 h-2 rounded-full bg-success animate-pulse"></span>
          Tailwind CSS v4 + DaisyUI 5 Color Configuration
        </div>
        <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight">
          Verifikasi Palet Warna <span class="text-primary">Light</span> &amp; <span class="text-secondary">Dark</span>
        </h1>
        <p class="text-base-content/70 max-w-2xl mx-auto text-base lg:text-lg leading-relaxed">
          Halaman ini memastikan seluruh variabel token warna (Brand, Base, dan Semantic) di <code class="px-2 py-0.5 rounded bg-base-300 text-sm font-mono">app.css</code> berfungsi aktif dan konsisten.
        </p>
        <div class="flex flex-wrap justify-center gap-3 pt-2">
          <button @click="counter++" class="btn btn-primary shadow-sm">
            Primary Action (Klik: {{ counter }})
          </button>
          <button class="btn btn-secondary shadow-sm">Secondary Action</button>
          <button class="btn btn-accent shadow-sm">Accent Button</button>
          <button class="btn btn-neutral shadow-sm">Neutral Button</button>
        </div>
      </section>

      <!-- Brand Palette Cards -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold tracking-tight flex items-center gap-2">
          <span>🎨</span> Brand &amp; Accent Tokens
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Primary -->
          <div class="card bg-primary text-primary-content shadow-sm">
            <div class="card-body p-5">
              <span class="text-xs uppercase font-bold tracking-wider opacity-80">Brand Primary</span>
              <h3 class="text-2xl font-black">Primary</h3>
              <p class="text-xs opacity-90 mt-2 font-mono">--color-primary</p>
              <div class="card-actions justify-end mt-4">
                <span class="badge bg-primary-content text-primary font-semibold text-xs">Primary Content</span>
              </div>
            </div>
          </div>

          <!-- Secondary -->
          <div class="card bg-secondary text-secondary-content shadow-sm">
            <div class="card-body p-5">
              <span class="text-xs uppercase font-bold tracking-wider opacity-80">Brand Secondary</span>
              <h3 class="text-2xl font-black">Secondary</h3>
              <p class="text-xs opacity-90 mt-2 font-mono">--color-secondary</p>
              <div class="card-actions justify-end mt-4">
                <span class="badge bg-secondary-content text-secondary font-semibold text-xs">Secondary Content</span>
              </div>
            </div>
          </div>

          <!-- Accent -->
          <div class="card bg-accent text-accent-content shadow-sm">
            <div class="card-body p-5">
              <span class="text-xs uppercase font-bold tracking-wider opacity-80">Brand Accent</span>
              <h3 class="text-2xl font-black">Accent</h3>
              <p class="text-xs opacity-90 mt-2 font-mono">--color-accent</p>
              <div class="card-actions justify-end mt-4">
                <span class="badge bg-accent-content text-accent font-semibold text-xs">Accent Content</span>
              </div>
            </div>
          </div>

          <!-- Neutral -->
          <div class="card bg-neutral text-neutral-content shadow-sm">
            <div class="card-body p-5">
              <span class="text-xs uppercase font-bold tracking-wider opacity-80">Neutral</span>
              <h3 class="text-2xl font-black">Neutral</h3>
              <p class="text-xs opacity-90 mt-2 font-mono">--color-neutral</p>
              <div class="card-actions justify-end mt-4">
                <span class="badge bg-neutral-content text-neutral font-semibold text-xs">Neutral Content</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Base Canvas & Surface Comparison -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold tracking-tight flex items-center gap-2">
          <span>🏛️</span> Base Background Hierarchies
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="p-6 rounded-box bg-base-100 border border-base-300 shadow-xs space-y-2">
            <div class="badge badge-outline text-xs font-mono">base-100</div>
            <h4 class="font-bold text-base">Surface / Card</h4>
            <p class="text-sm text-base-content/70">Warna lapisan kartu utama, modal dialog, dan dropdown.</p>
          </div>
          <div class="p-6 rounded-box bg-base-200 border border-base-300 shadow-xs space-y-2">
            <div class="badge badge-outline text-xs font-mono">base-200</div>
            <h4 class="font-bold text-base">Canvas Background</h4>
            <p class="text-sm text-base-content/70">Warna kanvas dasar halaman atau layout dashboard.</p>
          </div>
          <div class="p-6 rounded-box bg-base-300 border border-base-300 shadow-xs space-y-2">
            <div class="badge badge-outline text-xs font-mono">base-300</div>
            <h4 class="font-bold text-base">Divider &amp; Inactive</h4>
            <p class="text-sm text-base-content/70">Warna garis pemisah, border kontainer, atau state disabled.</p>
          </div>
        </div>
      </section>

      <!-- Semantic Alerts & Badges -->
      <section class="space-y-4">
        <h2 class="text-xl font-bold tracking-tight flex items-center gap-2">
          <span>🚦</span> Semantic States
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="alert alert-info shadow-xs p-3.5 flex items-center gap-2.5">
            <span class="font-semibold text-sm">ℹ️ Info state aktif</span>
          </div>
          <div class="alert alert-success shadow-xs p-3.5 flex items-center gap-2.5">
            <span class="font-semibold text-sm">✅ Success state aktif</span>
          </div>
          <div class="alert alert-warning shadow-xs p-3.5 flex items-center gap-2.5">
            <span class="font-semibold text-sm">⚠️ Warning state aktif</span>
          </div>
          <div class="alert alert-error shadow-xs p-3.5 flex items-center gap-2.5">
            <span class="font-semibold text-sm">⛔ Error state aktif</span>
          </div>
        </div>
      </section>

      <!-- Component Interactive Test -->
      <section class="card bg-base-100 border border-base-300 shadow-sm">
        <div class="card-body p-6 space-y-4">
          <h2 class="text-xl font-bold tracking-tight flex items-center gap-2">
            <span>⚙️</span> Interactive Component Test
          </h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <div class="space-y-2">
              <label class="text-sm font-semibold text-base-content">Test Input Utility (input-block)</label>
              <input 
                v-model="testInput" 
                type="text" 
                placeholder="Ketik sesuatu untuk tes input styling..." 
                class="input input-bordered input-block"
              />
              <p class="text-xs text-base-content/60" v-if="testInput">Hasil input: <span class="font-medium text-primary">{{ testInput }}</span></p>
            </div>
            <div class="stats stats-horizontal shadow-xs bg-base-200 border border-base-300">
              <div class="stat p-4">
                <div class="stat-title text-xs">Tema Aktif</div>
                <div class="stat-value text-xl capitalize text-primary">{{ currentTheme }}</div>
                <div class="stat-desc text-xs mt-1">DaisyUI Theme Plugin</div>
              </div>
              <div class="stat p-4">
                <div class="stat-title text-xs">Border Radius</div>
                <div class="stat-value text-xl text-secondary">0.5rem</div>
                <div class="stat-desc text-xs mt-1">--radius-* token</div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
