<script setup>
import { onMounted, ref } from 'vue'
import { useConversationStore } from '@/stores/app/conversation.store'
import { getInitials, formatRelativeTime, formatTime, formatPhone } from '@/utils/helper'
import { notify } from '@/plugins'

const p$conv = useConversationStore()
const replyInput = ref('')

onMounted(async () => {
  try {
    await p$conv.a$fetchList()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal memuat percakapan')
  }
})

const handleSearch = (e) => {
  p$conv.a$search(e.target.value)
}

const handleTabChange = (status) => {
  p$conv.a$filterByStatus(status)
}

const handleSelect = (id) => {
  p$conv.a$select(id)
}

const handleSend = () => {
  if (!replyInput.value.trim() || !p$conv.g$activeConversation) return
  p$conv.g$activeConversation.messages.push({
    id: Date.now(),
    sender: 'ai',
    text: replyInput.value.trim(),
    time: new Date().toISOString(),
    status: 'delivered',
  })
  p$conv.g$activeConversation.lastMessage = replyInput.value.trim()
  p$conv.g$activeConversation.lastMessageTime = new Date().toISOString()
  replyInput.value = ''
}
</script>

<template>
  <div class="h-full p-4 flex gap-4 bg-base-200 overflow-hidden select-none">
    <!-- ─── Kolom Kiri: WhatsApp Inbox Card ───────────────── -->
    <section class="w-80 lg:w-88 shrink-0 bg-base-100 rounded-2xl border border-base-300 shadow-xs flex flex-col h-full overflow-hidden">
      <!-- Header Inbox & Total Counter -->
      <div class="p-4 space-y-3 shrink-0">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="font-bold text-sm text-base-content tracking-tight">WhatsApp Inbox</h2>
          </div>
        </div>

        <!-- Search Bar ala Gambar 2 -->
        <div class="relative">
          <input
            type="text"
            placeholder="Search people and messages"
            class="input input-sm w-full pl-9 focus:outline-none text-xs rounded-xl bg-base-200 border-0 focus:ring-1 focus:ring-base-content/20 text-base-content placeholder:text-base-content/40"
            :value="p$conv.g$searchQuery"
            @input="handleSearch"
          />
          <div class="absolute left-3 top-2.5 text-base-content/40 pointer-events-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8" />
              <path d="m21 21-4.3-4.3" />
            </svg>
          </div>
        </div>

        <!-- Segmented Filter Pills ala Gambar 2 -->
        <div class="flex items-center gap-1.5 pt-0.5">
          <button
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors"
            :class="[p$conv.g$filterStatus === 'all' ? 'bg-base-200 text-base-content font-bold' : 'text-base-content/50 hover:text-base-content']"
            @click="handleTabChange('all')"
          >
            All
          </button>
          <button
            class="px-3.5 py-1.5 rounded-lg text-xs font-medium transition-colors flex items-center gap-1.5"
            :class="[p$conv.g$filterStatus === 'unread' ? 'bg-base-200 text-base-content font-bold' : 'text-base-content/50 hover:text-base-content']"
            @click="handleTabChange('unread')"
          >
            <span>Unread</span>
            <span
              v-if="p$conv.g$conversations.filter(c => c.unreadCount > 0).length"
              class="min-w-4.5 h-4.5 px-1 rounded-full bg-primary text-primary-content font-bold text-[10px] flex items-center justify-center shadow-xs"
            >
              {{ p$conv.g$conversations.filter(c => c.unreadCount > 0).length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Contact List Body -->
      <div class="flex-1 overflow-y-auto px-2 space-y-1">
        <!-- Loading State -->
        <div v-if="p$conv.g$loading" class="p-8">
          <BaseLoading text="Memuat percakapan..." />
        </div>

        <!-- Empty State -->
        <div v-else-if="p$conv.g$filteredList.length === 0" class="p-8">
          <BaseEmpty
            title="No conversations found"
            description="Tidak ada pesan yang cocok dengan pencarian Anda."
          />
        </div>

        <!-- Chat List Rows -->
        <div
          v-else
          v-for="chat in p$conv.g$filteredList"
          :key="chat.id"
          @click="handleSelect(chat.id)"
          class="p-3 px-3.5 flex items-center gap-3 cursor-pointer rounded-xl transition-colors duration-150"
          :class="[
            p$conv.g$activeId === chat.id
              ? 'bg-base-200 text-base-content font-medium'
              : 'hover:bg-base-200/50 text-base-content/80'
          ]"
        >
          <!-- Clean Avatar Initial -->
          <div class="w-10 h-10 rounded-full bg-base-200 text-base-content/80 font-bold text-xs flex items-center justify-center shrink-0">
            {{ getInitials(chat.customer.name) }}
          </div>

          <!-- Middle: Name & Clean Last Message -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
              <h3 class="font-semibold text-xs text-base-content truncate">
                {{ chat.customer.name }}
              </h3>
              <span class="text-[10px] text-base-content/40 font-mono shrink-0 ml-2">
                {{ formatRelativeTime(chat.lastMessageTime) }}
              </span>
            </div>

            <p class="text-[11px] text-base-content/50 truncate leading-tight">
              {{ chat.lastMessage }}
            </p>
          </div>

          <!-- Unread Counter Pill ( Primary Token) -->
          <div v-if="chat.unreadCount > 0" class="shrink-0 pl-1">
            <span class="w-4.5 h-4.5 rounded-full bg-primary text-primary-content font-bold text-[10px] flex items-center justify-center shadow-xs">
              {{ chat.unreadCount }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Kolom Kanan: Active Chat Room Card ────────── -->
    <section class="flex-1 bg-base-100 rounded-2xl border border-base-300 shadow-xs flex flex-col h-full overflow-hidden relative min-w-0">
      <!-- Empty State (Solid Clean, Tanpa Efek Neon Transparan) -->
      <div v-if="!p$conv.g$activeConversation" class="flex-1 flex flex-col items-center justify-center p-8 bg-base-100 space-y-4 select-none">
        <div class="w-16 h-16 rounded-full bg-base-200 text-primary shadow-xs flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
          </svg>
        </div>

        <div class="text-center space-y-1">
          <h3 class="font-bold text-base text-base-content tracking-tight">WhatsApp Inbox</h3>
          <p class="text-xs text-base-content/50">Select a conversation to view messages.</p>
        </div>

        <div class="pt-6">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-base-100 border border-base-300 text-[11px] text-base-content/60 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-base-content/40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
            <span>Messages synchronized and secured via WhatsApp Gateway.</span>
          </div>
        </div>
      </div>

      <!-- Jika obrolan aktif tersedia -->
      <template v-else>
        <!-- Chat Header Ringkas & Bersih -->
        <header class="h-14 px-5 bg-base-100 border-b border-base-300 flex items-center justify-between shrink-0 z-10">
          <div class="flex items-center gap-3 min-w-0">
            <!-- Avatar Inisial -->
            <div class="w-9 h-9 rounded-full bg-base-200 text-base-content/80 font-bold text-xs flex items-center justify-center shrink-0">
              {{ getInitials(p$conv.g$activeConversation.customer.name) }}
            </div>

            <!-- Details Kontak -->
            <div class="min-w-0">
              <h2 class="font-semibold text-xs text-base-content truncate leading-tight">
                {{ p$conv.g$activeConversation.customer.name }}
              </h2>
              <p class="text-[11px] text-base-content/45 font-mono leading-tight mt-0.5">
                {{ formatPhone(p$conv.g$activeConversation.customer.phone) }}
              </p>
            </div>
          </div>

          <!-- Header Right Actions: Indikator Online Halus -->
          <div class="flex items-center gap-2">
            <div class="flex items-center gap-1.5 text-[11px] text-base-content/50 font-medium">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Online</span>
            </div>
          </div>
        </header>

        <!-- Scrollable Message Body (Lapang & Tenang) -->
        <div class="flex-1 overflow-y-auto p-6 space-y-3.5 bg-base-200/40 dark:bg-base-200">
          <!-- Date Separator -->
          <div class="flex justify-center my-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-medium bg-base-100 dark:bg-base-300 border border-base-300 text-base-content/50 shadow-2xs">
              Hari ini
            </span>
          </div>

          <!-- Message Bubbles (Tanpa Header Internal Berjejal) -->
          <div
            v-for="msg in p$conv.g$activeConversation.messages"
            :key="msg.id"
            class="flex"
            :class="[msg.sender === 'customer' ? 'justify-start' : 'justify-end']"
          >
            <!-- Inbound (Customer Bubble - Warna Pekat Solid, Berkontras Tinggi di Dark Mode) -->
            <div
              v-if="msg.sender === 'customer'"
              class="max-w-md bg-base-100 dark:bg-base-300 text-base-content rounded-2xl rounded-tl-xs p-3 px-3.5 shadow-sm space-y-1"
            >
              <p class="text-xs leading-relaxed text-base-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>
              <div class="flex items-center justify-end text-[10px] text-base-content/50 font-mono pt-0.5">
                <span>{{ formatTime(msg.time) }}</span>
              </div>
            </div>

            <!-- Outbound  -->
            <div
              v-else
              class="max-w-md bg-primary text-primary-content rounded-2xl rounded-tr-xs p-3 px-3.5 shadow-sm space-y-1"
            >
              <p class="text-xs leading-relaxed text-primary-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>

              <div class="flex items-center justify-end gap-1.5 text-[10px] text-primary-content/80 font-mono pt-0.5">
                <span>{{ formatTime(msg.time) }}</span>
                <!-- Double Checkmark Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-primary-content" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m3 12 5 5L20 5" />
                  <path d="m11 12 3 3L22 7" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Input Bar (WhatsApp Web Minimalist Style) -->
        <footer class="p-3 px-4 bg-base-100 border-t border-base-300 shrink-0">
          <div class="flex items-center gap-2">
            <!-- Attachment Icon -->
            <button class="btn btn-ghost btn-circle btn-sm text-base-content/50 hover:text-base-content" title="Lampirkan berkas">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48" />
              </svg>
            </button>

            <!-- Message Input Rounded -->
            <input
              v-model="replyInput"
              @keydown.enter="handleSend"
              type="text"
              placeholder="Tulis pesan..."
              class="input input-sm flex-1 focus:outline-none text-xs rounded-xl bg-base-200 border-0 focus:ring-1 focus:ring-base-content/20 text-base-content placeholder:text-base-content/40 px-3.5"
            />

            <!-- Send Button (Kuning Aksen, Jelas Terlihat di Dark Mode) -->
            <button
              type="button"
              @click="handleSend"
              :disabled="!replyInput.trim()"
              class="btn btn-sm px-4 rounded-xl flex items-center gap-1.5 text-xs font-semibold transition-all duration-150 disabled:!opacity-90 disabled:!bg-base-300 disabled:!text-base-content/60 disabled:!border-base-300/80 disabled:cursor-not-allowed"
              :class="[
                replyInput.trim()
                  ? 'btn-accent text-accent-content font-bold shadow-sm hover:brightness-105 active:scale-95 cursor-pointer'
                  : 'bg-base-300 text-base-content/60 border border-base-300/80'
              ]"
            >
              <span>Kirim</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m22 2-7 20-4-9-9-4Z" />
                <path d="M22 2 11 13" />
              </svg>
            </button>
          </div>
        </footer>
      </template>
    </section>
  </div>
</template>
