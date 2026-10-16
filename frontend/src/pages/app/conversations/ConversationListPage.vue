<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useConversationStore } from '@/stores/app/conversation.store'
import { getInitials, formatRelativeTime, formatTime, formatPhone } from '@/utils/helper'
import { notify, echo } from '@/plugins'
import * as s$conversation from '@/services/app/conversation.service'

const p$conv = useConversationStore()
const replyInput = ref('')
const messageContainer = ref(null)
const sending = ref(false)

let typingTimeout = null
let isTypingActive = false

const onAdminTyping = async () => {
  const activeConv = p$conv.g$activeConversation
  if (!activeConv) return

  // Saat karakter pertama diketik: panggil sendTyping start dan set local state
  if (!isTypingActive) {
    isTypingActive = true
    try {
      await s$conversation.sendTyping(activeConv.id, 'start')
      activeConv.handler = 'human'
      activeConv.status = 'escalated'
    } catch (e) {
      console.warn('Failed to send typing start presence', e)
    }
  }

  // Reset timer debounce 3.5 detik idle
  if (typingTimeout) {
    clearTimeout(typingTimeout)
  }
  typingTimeout = setTimeout(() => {
    onAdminStopTyping()
  }, 3500)
}

const onAdminStopTyping = async () => {
  if (typingTimeout) {
    clearTimeout(typingTimeout)
    typingTimeout = null
  }

  if (isTypingActive) {
    isTypingActive = false
    const activeConv = p$conv.g$activeConversation
    if (activeConv) {
      try {
        await s$conversation.sendTyping(activeConv.id, 'stop')
      } catch (e) {
        console.warn('Failed to send typing stop presence', e)
      }
    }
  }
}

const scrollToBottom = async () => {
  await nextTick()
  if (messageContainer.value) {
    messageContainer.value.scrollTop = messageContainer.value.scrollHeight
  }
}

onMounted(async () => {
  try {
    await p$conv.a$fetchList()
    scrollToBottom()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to load conversations')
  }

  // Subscribe ke channel global conversations untuk real-time sync list sidebar & chat room
  echo
    .channel('conversations')
    .listen('.MessageReceived', (e) => {
      const isNew = p$conv.a$handleIncomingBroadcast(e)
      if (isNew) {
        scrollToBottom()
      }
    })
    .listen('.ConversationUpdated', (e) => {
      p$conv.a$handleConversationUpdatedBroadcast(e)
    })
})

onUnmounted(() => {
  if (typingTimeout) {
    clearTimeout(typingTimeout)
  }
  if (isTypingActive && p$conv.g$activeId) {
    s$conversation.sendTyping(p$conv.g$activeId, 'stop').catch(() => {})
  }
  echo.leaveChannel('conversations')
})

watch(
  () => p$conv.g$activeConversation?.messages?.length,
  () => {
    scrollToBottom()
  }
)

const handleSearch = (e) => {
  p$conv.a$search(e.target.value)
}

const handleTabChange = (status) => {
  p$conv.a$filterByStatus(status)
}

const handleSelect = async (id) => {
  if (isTypingActive) {
    await onAdminStopTyping()
  }
  replyInput.value = ''
  try {
    await p$conv.a$select(id)
    scrollToBottom()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to select conversation')
  }
}

const handleSend = async () => {
  if (!replyInput.value.trim() || !p$conv.g$activeConversation || sending.value) return

  // Bersihkan debounce timer & typing presence
  if (typingTimeout) {
    clearTimeout(typingTimeout)
    typingTimeout = null
  }
  isTypingActive = false

  const text = replyInput.value.trim()
  replyInput.value = ''
  sending.value = true
  try {
    await p$conv.a$sendMessage(text)
    scrollToBottom()
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to send message')
  } finally {
    sending.value = false
  }
}

const handleSync = async () => {
  try {
    const res = await p$conv.a$sync()
    notify.success(res?.message ?? 'Conversations synchronized successfully')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to synchronize with WhatsApp')
  }
}

const updatingHandler = ref(false)

const handleToggleHandler = async (targetMode) => {
  const activeConv = p$conv.g$activeConversation
  if (!activeConv || updatingHandler.value) return

  // Jika memindahkan ke AI saat sedang mengetik, matikan typing presence
  if (targetMode === 'ai' && isTypingActive) {
    await onAdminStopTyping()
  }

  updatingHandler.value = true
  try {
    await s$conversation.updateHandler(activeConv.id, targetMode)
    activeConv.handler = targetMode
    activeConv.status = targetMode === 'ai' ? 'all' : 'escalated'
    notify.success(
      targetMode === 'ai'
        ? 'Percakapan berhasil dikembalikan ke mode AI'
        : 'Percakapan berhasil diambil alih ke mode Human'
    )
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal mengubah mode percakapan')
  } finally {
    updatingHandler.value = false
  }
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
          <!-- Sync Button -->
          <button
            type="button"
            @click="handleSync"
            :disabled="p$conv.g$syncing"
            class="btn btn-ghost btn-xs rounded-lg gap-1 text-[11px] text-base-content/60 hover:text-base-content hover:bg-base-200 transition-colors"
            title="Sync conversations from WhatsApp Gateway"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-3.5 h-3.5"
              :class="{ 'animate-spin': p$conv.g$syncing }"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
              <path d="M3 3v5h5" />
              <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
              <path d="M16 21h5v-5" />
            </svg>
            <span>{{ p$conv.g$syncing ? 'Syncing...' : 'Sync' }}</span>
          </button>
        </div>

        <!-- Search Bar-->
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
              v-if="p$conv.g$conversations.filter(c => (c.unreadCount ?? c.unread_count ?? 0) > 0).length"
              class="min-w-4.5 h-4.5 px-1 rounded-full bg-primary text-primary-content font-bold text-[10px] flex items-center justify-center shadow-xs"
            >
              {{ p$conv.g$conversations.filter(c => (c.unreadCount ?? c.unread_count ?? 0) > 0).length }}
            </span>
          </button>
        </div>
      </div>

      <!-- Contact List Body -->
      <div class="flex-1 overflow-y-auto px-2 space-y-1">
        <!-- Loading State -->
        <div v-if="p$conv.g$loading && p$conv.g$conversations.length === 0" class="p-8">
          <BaseLoading text="Loading conversations..." />
        </div>

        <!-- Empty State -->
        <div v-else-if="p$conv.g$filteredList.length === 0" class="p-8">
          <BaseEmpty
            title="No conversations found"
            description="No messages match your search query."
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
            {{ getInitials(chat.customer?.name ?? chat.name) }}
          </div>

          <!-- Middle: Name & Clean Last Message -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-1">
              <h3 class="font-semibold text-xs text-base-content truncate">
                {{ chat.customer?.name ?? chat.name }}
              </h3>
              <span class="text-[10px] text-base-content/40 font-medium tabular-nums shrink-0 ml-2">
                {{ formatRelativeTime(chat.lastMessageTime ?? chat.last_message_time) }}
              </span>
            </div>

            <p class="text-[11px] text-base-content/50 truncate leading-tight">
              {{ chat.lastMessage ?? chat.last_message }}
            </p>
          </div>

          <!-- Unread Counter Pill ( Primary Token) -->
          <div v-if="(chat.unreadCount ?? chat.unread_count ?? 0) > 0" class="shrink-0 pl-1">
            <span class="w-4.5 h-4.5 rounded-full bg-primary text-primary-content font-bold text-[10px] flex items-center justify-center shadow-xs">
              {{ chat.unreadCount ?? chat.unread_count }}
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
              {{ getInitials(p$conv.g$activeConversation.customer?.name ?? p$conv.g$activeConversation.name) }}
            </div>

            <!-- Details Kontak -->
            <div class="min-w-0">
              <h2 class="font-semibold text-xs text-base-content truncate leading-tight">
                {{ p$conv.g$activeConversation.customer?.name ?? p$conv.g$activeConversation.name }}
              </h2>
              <p class="text-[11px] text-base-content/45 font-medium tabular-nums leading-tight mt-0.5">
                {{ formatPhone(p$conv.g$activeConversation.customer?.phone ?? p$conv.g$activeConversation.phone) }}
              </p>
            </div>
          </div>

          <!-- Header Right Actions: Mode Switcher & Status -->
          <div class="flex items-center gap-2.5">
            <!-- Mode Switcher Pill -->
            <div class="flex items-center gap-1.5 bg-base-200/90 py-1 pl-2.5 pr-1.5 rounded-xl border border-base-300 shadow-2xs">
              <!-- Saat Mode AI Aktif -->
              <template v-if="p$conv.g$activeConversation.handler !== 'human'">
                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                  <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                  </span>
                  <span>AI Active</span>
                </div>
                <button
                  type="button"
                  @click="handleToggleHandler('human')"
                  :disabled="updatingHandler"
                  class="btn btn-ghost btn-xs rounded-lg text-[10px] h-6 min-h-6 px-2 font-medium text-base-content/70 hover:text-base-content hover:bg-base-100 shadow-2xs transition-all flex items-center gap-1"
                  title="Ambil alih percakapan ini ke mode manual (Human)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-base-content/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2" />
                    <circle cx="12" cy="7" r="4" />
                  </svg>
                  <span>Ambil Alih</span>
                </button>
              </template>

              <!-- Saat Mode Human Aktif -->
              <template v-else>
                <div class="flex items-center gap-1.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                  <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                  <span>Human Mode</span>
                </div>
                <button
                  type="button"
                  @click="handleToggleHandler('ai')"
                  :disabled="updatingHandler"
                  class="btn btn-ghost btn-xs rounded-lg text-[10px] h-6 min-h-6 px-2 font-medium text-base-content/70 hover:text-base-content hover:bg-base-100 shadow-2xs transition-all flex items-center gap-1"
                  title="Kembalikan percakapan ini ke auto-reply AI"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-base-content/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 8V4H8" />
                    <rect width="16" height="12" x="4" y="8" rx="2" />
                    <path d="M2 14h2" />
                    <path d="M20 14h2" />
                    <path d="M15 13v2" />
                    <path d="M9 13v2" />
                  </svg>
                  <span>Kembalikan ke AI</span>
                </button>
              </template>
            </div>

            <!-- Online Indicator Halus -->
            <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-base-content/50 font-medium pl-1 border-l border-base-300">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <span>Online</span>
            </div>
          </div>
        </header>

        <!-- Scrollable Message Body (Lapang & Tenang) -->
        <div ref="messageContainer" class="flex-1 overflow-y-auto p-6 space-y-3.5 bg-base-200/40 dark:bg-base-200">
          <!-- Date Separator -->
          <div class="flex justify-center my-2">
            <span class="px-3 py-1 rounded-full text-[10px] font-medium bg-base-100 dark:bg-base-300 border border-base-300 text-base-content/50 shadow-2xs">
              Chat History
            </span>
          </div>

          <!-- Message Bubbles (Tanpa Header Internal Berjejal) -->
          <div
            v-for="msg in (p$conv.g$activeConversation.messages || [])"
            :key="msg.client_id || msg.id"
            class="flex"
            :class="[(msg.sender === 'customer' || msg.sender_type === 'customer') ? 'justify-start' : 'justify-end']"
          >
            <!-- Inbound (Customer Bubble - Warna Pekat Solid, Berkontras Tinggi di Dark Mode) -->
            <div
              v-if="(msg.sender === 'customer' || msg.sender_type === 'customer')"
              class="max-w-md bg-base-100 dark:bg-base-300 text-base-content rounded-2xl rounded-tl-xs p-3 px-3.5 shadow-sm space-y-1"
            >
              <p class="text-xs leading-relaxed text-base-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>
              <div class="flex items-center justify-end text-[10px] text-base-content/50 font-medium tabular-nums pt-0.5">
                <span>{{ formatTime(msg.time ?? msg.sent_at) }}</span>
              </div>
            </div>

            <!-- Outbound  -->
            <div
              v-else
              class="max-w-md bg-primary text-primary-content rounded-2xl rounded-tr-xs p-3 px-3.5 shadow-sm space-y-1 transition-opacity duration-150"
              :class="{ 'opacity-85': msg.status === 'pending' || msg.status === 'sending' }"
            >
              <p class="text-xs leading-relaxed text-primary-content select-text whitespace-pre-wrap">
                {{ msg.text }}
              </p>

              <div class="flex items-center justify-end gap-1.5 text-[10px] text-primary-content/80 font-medium tabular-nums pt-0.5">
                <span>{{ formatTime(msg.time ?? msg.sent_at) }}</span>

                <!-- Status Indicator: Pending (Clock) vs Delivered (Centang Dua) vs Failed -->
                <svg
                  v-if="msg.status === 'pending' || msg.status === 'sending'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-3 h-3 text-primary-content/80 animate-pulse shrink-0"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  title="Sending..."
                >
                  <circle cx="12" cy="12" r="10" />
                  <polyline points="12 6 12 12 16 14" />
                </svg>

                <svg
                  v-else-if="msg.status === 'failed'"
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-3.5 h-3.5 text-error shrink-0"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  title="Failed to deliver"
                >
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>

                <!-- Double Checkmark (Centang Dua Resmi Terkirim) -->
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-3.5 h-3.5 text-primary-content shrink-0"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2.2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  title="Delivered"
                >
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
            <button class="btn btn-ghost btn-circle btn-sm text-base-content/50 hover:text-base-content" title="Attach file">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48" />
              </svg>
            </button>

            <!-- Message Input Rounded -->
            <input
              v-model="replyInput"
              @keydown.enter="handleSend"
              @input="onAdminTyping"
              @blur="onAdminStopTyping"
              type="text"
              placeholder="Type a message..."
              class="input input-sm flex-1 focus:outline-none text-xs rounded-xl bg-base-200 border-0 focus:ring-1 focus:ring-base-content/20 text-base-content placeholder:text-base-content/40 px-3.5"
            />

            <!-- Send Button (Kuning Aksen, Jelas Terlihat di Dark Mode) -->
            <button
              type="button"
              @click="handleSend"
              :disabled="!replyInput.trim() || sending"
              class="btn btn-sm px-4 rounded-xl flex items-center gap-1.5 text-xs font-semibold transition-all duration-150 disabled:!opacity-90 disabled:!bg-base-300 disabled:!text-base-content/60 disabled:!border-base-300/80 disabled:cursor-not-allowed"
              :class="[
                replyInput.trim() && !sending
                  ? 'btn-accent text-accent-content font-bold shadow-sm hover:brightness-105 active:scale-95 cursor-pointer'
                  : 'bg-base-300 text-base-content/60 border border-base-300/80'
              ]"
            >
              <span>{{ sending ? 'Sending...' : 'Send' }}</span>
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
