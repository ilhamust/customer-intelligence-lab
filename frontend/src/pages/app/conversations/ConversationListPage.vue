<script setup>
import { onMounted, ref } from 'vue'
import { useConversationStore } from '@/stores/app/conversation.store'
import { getInitials, formatRelativeTime, formatTime, formatPhone } from '@/utils/helper'

const p$conv = useConversationStore()
const replyInput = ref('')

onMounted(async () => {
  await p$conv.a$fetchList()
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
</script>

<template>
  <div class="h-full flex overflow-hidden bg-base-100 select-none">
    <!-- ─── Kolom Kiri: Sidebar Daftar Percakapan (380px) ─────── -->
    <section class="w-96 shrink-0 border-r border-base-300 flex flex-col h-full bg-base-100">
      <!-- Header Inbox & Total Counter -->
      <div class="p-4 pb-3 border-b border-base-300 space-y-3 shrink-0">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="font-bold text-base text-base-content tracking-tight">Percakapan</h2>
            <BaseBadge variant="neutral" size="sm">
              {{ p$conv.g$conversations.length }}
            </BaseBadge>
          </div>
          <span class="text-[11px] text-base-content/50 font-medium">WhatsApp Web</span>
        </div>

        <!-- Search Bar -->
        <div class="relative">
          <input
            type="text"
            placeholder="Cari nama, nomor WA, ID meter..."
            class="input input-sm input-bordered w-full pl-9 focus:border-primary focus:outline-none text-xs rounded-lg bg-base-200/50"
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

        <!-- Filter Chips -->
        <div class="flex items-center gap-1.5 pt-0.5">
          <button
            class="btn btn-xs rounded-full font-medium transition-colors"
            :class="[p$conv.g$filterStatus === 'all' ? 'btn-primary text-primary-content' : 'btn-ghost text-base-content/60 hover:bg-base-200']"
            @click="handleTabChange('all')"
          >
            Semua
          </button>
          <button
            class="btn btn-xs rounded-full font-medium transition-colors"
            :class="[p$conv.g$filterStatus === 'unread' ? 'btn-primary text-primary-content' : 'btn-ghost text-base-content/60 hover:bg-base-200']"
            @click="handleTabChange('unread')"
          >
            Belum Dibalas
          </button>
          <button
            class="btn btn-xs rounded-full font-medium transition-colors"
            :class="[p$conv.g$filterStatus === 'escalated' ? 'btn-primary text-primary-content' : 'btn-ghost text-base-content/60 hover:bg-base-200']"
            @click="handleTabChange('escalated')"
          >
            Eskalasi
          </button>
        </div>
      </div>

      <!-- Contact List Body -->
      <div class="flex-1 overflow-y-auto divide-y divide-base-300/40">
        <!-- Loading State -->
        <div v-if="p$conv.g$loading" class="p-8">
          <BaseLoading text="Memuat percakapan..." />
        </div>

        <!-- Empty State -->
        <div v-else-if="p$conv.g$filteredList.length === 0" class="p-8">
          <BaseEmpty
            title="Tidak ada percakapan"
            description="Tidak ada pesan yang cocok dengan kriteria filter saat ini."
          />
        </div>

        <!-- Chat List Rows -->
        <div
          v-else
          v-for="chat in p$conv.g$filteredList"
          :key="chat.id"
          @click="handleSelect(chat.id)"
          class="p-3.5 px-4 flex items-start gap-3 cursor-pointer transition-colors hover:bg-base-200/60"
          :class="[
            p$conv.g$activeId === chat.id
              ? 'bg-primary/10 border-l-4 border-primary'
              : 'border-l-4 border-transparent'
          ]"
        >
          <!-- Avatar Initial -->
          <div class="w-11 h-11 rounded-full bg-primary/20 text-primary font-bold text-sm flex items-center justify-center shrink-0 border border-primary/20">
            {{ getInitials(chat.customer.name) }}
          </div>

          <!-- Middle: Name & Last Message -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-0.5">
              <h3 class="font-semibold text-xs text-base-content truncate">
                {{ chat.customer.name }}
              </h3>
              <span class="text-[10px] text-base-content/50 font-mono shrink-0 ml-2">
                {{ formatRelativeTime(chat.lastMessageTime) }}
              </span>
            </div>

            <div class="flex items-center gap-1.5 mb-1">
              <span class="text-[10px] px-1.5 py-0.2 rounded bg-base-200 text-base-content/60 font-mono">
                {{ chat.customer.meterNo }}
              </span>
              <span v-if="chat.handler === 'human'" class="text-[9px] font-bold text-warning uppercase px-1 rounded bg-warning/10">
                Agen
              </span>
              <span v-else class="text-[9px] font-bold text-primary uppercase px-1 rounded bg-primary/10">
                AI
              </span>
            </div>

            <p class="text-[11px] text-base-content/60 truncate leading-relaxed">
              {{ chat.lastMessage }}
            </p>
          </div>

          <!-- Unread Badge -->
          <div v-if="chat.unreadCount > 0" class="shrink-0 pt-1">
            <span class="badge badge-xs bg-primary text-primary-content font-bold px-1.5 py-0.5">
              {{ chat.unreadCount }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- ─── Kolom Kanan: Ruang Obrolan Aktif (Chat Room) ────────── -->
    <section class="flex-1 flex flex-col h-full bg-base-200/40 relative min-w-0">
      <!-- Jika tidak ada obrolan yang dipilih -->
      <div v-if="!p$conv.g$activeConversation" class="flex-1 flex items-center justify-center p-8 bg-base-100">
        <BaseEmpty
          title="Pilih salah satu percakapan"
          description="Klik percakapan di panel sebelah kiri untuk membaca dan meninjau riwayat obrolan pelanggan."
        />
      </div>

      <!-- Jika obrolan aktif tersedia -->
      <template v-else>
        <!-- Chat Header -->
        <header class="h-16 px-6 bg-base-100 border-b border-base-300 flex items-center justify-between shrink-0 z-10">
          <div class="flex items-center gap-3 min-w-0">
            <!-- Avatar -->
            <div class="w-10 h-10 rounded-full bg-primary/20 text-primary font-bold text-sm flex items-center justify-center shrink-0 border border-primary/20">
              {{ getInitials(p$conv.g$activeConversation.customer.name) }}
            </div>

            <!-- Details -->
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <h2 class="font-bold text-sm text-base-content truncate">
                  {{ p$conv.g$activeConversation.customer.name }}
                </h2>
                <span class="text-[10px] font-semibold text-base-content/60 px-1.5 py-0.5 rounded bg-base-200 border border-base-300">
                  {{ p$conv.g$activeConversation.customer.segment }}
                </span>
              </div>
              <p class="text-[11px] text-base-content/50 font-mono mt-0.5 flex items-center gap-2">
                <span>{{ formatPhone(p$conv.g$activeConversation.customer.phone) }}</span>
                <span>&bull;</span>
                <span>ID: {{ p$conv.g$activeConversation.customer.meterNo }}</span>
              </p>
            </div>
          </div>

          <!-- Header Right Actions -->
          <div class="flex items-center gap-2">
            <span class="text-[11px] text-success font-medium flex items-center gap-1.5 bg-success/10 px-2.5 py-1 rounded-full">
              <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
              WhatsApp Aktif
            </span>
          </div>
        </header>

        <!-- Scrollable Message Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4">
          <!-- Date Separator -->
          <div class="flex justify-center my-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-semibold bg-base-100 border border-base-300 text-base-content/60 shadow-2xs">
              Hari ini
            </span>
          </div>

          <!-- Message Bubbles -->
          <div
            v-for="msg in p$conv.g$activeConversation.messages"
            :key="msg.id"
            class="flex"
            :class="[msg.sender === 'customer' ? 'justify-start' : 'justify-end']"
          >
            <!-- Inbound (Customer) -->
            <div
              v-if="msg.sender === 'customer'"
              class="max-w-lg bg-base-100 text-base-content border border-base-300 rounded-2xl rounded-tl-xs p-3.5 shadow-2xs space-y-1.5"
            >
              <p class="text-xs leading-relaxed text-base-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>
              <div class="flex items-center justify-end text-[10px] text-base-content/40 font-mono pt-0.5">
                <span>{{ formatTime(msg.time) }}</span>
              </div>
            </div>

            <!-- Outbound (AI / Agent) -->
            <div
              v-else
              class="max-w-lg bg-primary/10 text-base-content border border-primary/25 rounded-2xl rounded-tr-xs p-3.5 shadow-2xs space-y-1.5"
            >
              <!-- AI Badge indicator -->
              <div class="flex items-center justify-between gap-3 text-[10px] text-primary font-semibold pb-1 border-b border-primary/15">
                <span class="flex items-center gap-1">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8V4H8" /><rect width="16" height="12" x="4" y="8" rx="2" /><path d="M2 14h2" /><path d="M20 14h2" /><path d="M15 13v2" /><path d="M9 13v2" />
                  </svg>
                  Dijawab oleh AI Copilot
                </span>
                <span v-if="msg.intent" class="text-[9px] font-mono text-base-content/50">
                  {{ msg.intent }}
                </span>
              </div>

              <p class="text-xs leading-relaxed text-base-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>

              <div class="flex items-center justify-end gap-1 text-[10px] text-base-content/40 font-mono pt-0.5">
                <span>{{ formatTime(msg.time) }}</span>
                <!-- Double check status -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="m3 12 5 5L20 5" />
                  <path d="m11 12 3 3L22 7" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Input Bar (WhatsApp Web Style) -->
        <footer class="p-3 px-4 bg-base-100 border-t border-base-300 shrink-0">
          <div class="flex items-center gap-2.5">
            <!-- Attachment Icon -->
            <button class="btn btn-ghost btn-circle btn-sm text-base-content/50 hover:text-base-content" title="Lampirkan berkas">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48" />
              </svg>
            </button>

            <!-- Message Input -->
            <input
              v-model="replyInput"
              type="text"
              placeholder="Tulis pesan balasan ke WhatsApp pelanggan..."
              class="input input-sm input-bordered flex-1 focus:border-primary focus:outline-none text-xs rounded-lg bg-base-200/40"
            />

            <!-- Send Button -->
            <button
              class="btn btn-sm btn-primary px-4 rounded-lg flex items-center gap-1.5 text-xs font-semibold"
              :disabled="!replyInput.trim()"
            >
              <span>Kirim</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m22 2-7 20-4-9-9-4Z" />
                <path d="M22 2 11 13" />
              </svg>
            </button>
          </div>

          <div class="flex items-center justify-between text-[10px] text-base-content/40 pt-1.5 px-1 font-medium">
            <span>Mode Monitoring Back Office &bull; Pesan otomatis tersinkronisasi via WhatsApp Gateway</span>
            <span>Tekan Enter untuk mengirim</span>
          </div>
        </footer>
      </template>
    </section>
  </div>
</template>
