// src/stores/app/conversation.store.js
import { ref, computed } from 'vue'
import { defineStore, acceptHMRUpdate } from 'pinia'
import * as s$conversation from '@/services/app/conversation.service'

export const useConversationStore = defineStore('conversation', () => {
  // ─── State ───────────────────────────────────────────
  const conversations = ref([])
  const activeId = ref(null)
  const searchQuery = ref('')
  const filterStatus = ref('all') // 'all' | 'unread' | 'escalated'
  const loading = ref(false)
  const syncing = ref(false)

  // ─── Getters ─────────────────────────────────────────
  const g$conversations = computed(() => conversations.value)

  const g$activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeId.value) || null
  )

  const g$filteredList = computed(() => {
    let result = conversations.value

    // Filter status tab
    if (filterStatus.value === 'unread') {
      result = result.filter((c) => (c.unreadCount ?? c.unread_count ?? 0) > 0)
    } else if (filterStatus.value === 'escalated') {
      result = result.filter((c) => c.status === 'escalated' || c.handler === 'human')
    }

    // Filter keyword pencarian
    const q = searchQuery.value.trim().toLowerCase()
    if (q) {
      result = result.filter(
        (c) =>
          (c.customer?.name && c.customer.name.toLowerCase().includes(q)) ||
          (c.customer?.phone && c.customer.phone.includes(q)) ||
          (c.name && c.name.toLowerCase().includes(q)) ||
          (c.phone && c.phone.includes(q)) ||
          (c.lastMessage && c.lastMessage.toLowerCase().includes(q)) ||
          (c.last_message && c.last_message.toLowerCase().includes(q))
      )
    }

    return result
  })

  const g$loading = computed(() => loading.value)
  const g$syncing = computed(() => syncing.value)
  const g$activeId = computed(() => activeId.value)
  const g$filterStatus = computed(() => filterStatus.value)
  const g$searchQuery = computed(() => searchQuery.value)

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async () => {
    loading.value = true
    try {
      const res = await s$conversation.list({
        search: searchQuery.value || undefined,
        status: filterStatus.value !== 'all' ? filterStatus.value : undefined,
      })

      const listData = res?.data?.data ?? res?.data ?? []
      conversations.value = Array.isArray(listData) ? listData : []

      // Otomatis pilih item pertama jika belum ada yang aktif
      if (!activeId.value && conversations.value.length > 0) {
        await a$select(conversations.value[0].id)
      } else if (activeId.value) {
        // Refresh messages of active conversation if present
        const active = conversations.value.find((c) => c.id === activeId.value)
        if (active && (!active.messages || active.messages.length === 0)) {
          await a$fetchMessages(activeId.value)
        }
      }
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$fetchMessages = async (id) => {
    if (!id) return
    try {
      const res = await s$conversation.getMessages(id)
      const msgs = res?.data ?? []
      const target = conversations.value.find((c) => c.id === id)
      if (target) {
        target.messages = msgs
      }
    } catch (e) {
      console.error('Failed to fetch messages for conversation', id, e)
    }
  }

  const a$select = async (id) => {
    activeId.value = id

    // Tandai pesan sudah dibaca saat dibuka
    const target = conversations.value.find((c) => c.id === id)
    if (target && (target.unreadCount > 0 || target.unread_count > 0)) {
      target.unreadCount = 0
      target.unread_count = 0
    }

    await a$fetchMessages(id)
  }

  const a$sendMessage = async (text) => {
    if (!activeId.value || !text?.trim()) return

    const convId = activeId.value
    const trimmed = text.trim()
    const target = conversations.value.find((c) => c.id === convId)

    // Optimistic message bubble
    const tempMsg = {
      id: 'temp-' + Date.now(),
      conversation_id: convId,
      sender: 'human',
      sender_type: 'human',
      is_from_me: true,
      text: trimmed,
      time: new Date().toISOString(),
      sent_at: new Date().toISOString(),
      status: 'delivered',
    }

    if (target) {
      if (!Array.isArray(target.messages)) {
        target.messages = []
      }
      target.messages.push(tempMsg)
      target.lastMessage = trimmed
      target.last_message = trimmed
      target.lastMessageTime = tempMsg.time
      target.last_message_time = tempMsg.time
    }

    try {
      const res = await s$conversation.send(convId, trimmed, 'human')
      const saved = res?.data
      if (saved && target) {
        const idx = target.messages.findIndex((m) => m.id === tempMsg.id)
        if (idx !== -1) {
          target.messages[idx] = {
            id: saved.id,
            conversation_id: convId,
            sender: saved.sender_type ?? 'human',
            sender_type: saved.sender_type ?? 'human',
            is_from_me: true,
            text: saved.text,
            time: saved.sent_at ?? new Date().toISOString(),
            sent_at: saved.sent_at ?? new Date().toISOString(),
            status: saved.status ?? 'delivered',
          }
        }
      }
      return saved
    } catch (err) {
      tempMsg.status = 'failed'
      throw err
    }
  }

  const a$sync = async (deviceId = null) => {
    syncing.value = true
    try {
      const res = await s$conversation.sync(deviceId)
      await a$fetchList()
      return res?.data
    } catch (e) {
      throw e?.message ?? e
    } finally {
      syncing.value = false
    }
  }

  const a$search = (keyword) => {
    searchQuery.value = keyword
  }

  const a$filterByStatus = (status) => {
    filterStatus.value = status
  }

  return {
    // state
    conversations,
    activeId,
    searchQuery,
    filterStatus,
    loading,
    syncing,
    // getters
    g$conversations,
    g$activeConversation,
    g$filteredList,
    g$loading,
    g$syncing,
    g$activeId,
    g$filterStatus,
    g$searchQuery,
    // actions
    a$fetchList,
    a$fetchMessages,
    a$select,
    a$sendMessage,
    a$sync,
    a$search,
    a$filterByStatus,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useConversationStore, import.meta.hot))
}
