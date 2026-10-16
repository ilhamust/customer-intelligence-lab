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

    // Buat client_id unik yang stabil untuk Vue key agar bubble tidak hilang-muncul
    const clientKey = 'temp-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6)

    // Optimistic message bubble dengan status 'pending'
    const tempMsg = {
      id: clientKey,
      client_id: clientKey,
      conversation_id: convId,
      sender: 'human',
      sender_type: 'human',
      is_from_me: true,
      text: trimmed,
      time: new Date().toISOString(),
      sent_at: new Date().toISOString(),
      status: 'pending',
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
        const idx = target.messages.findIndex(
          (m) => m.client_id === clientKey || m.id === clientKey || m.id === saved.id
        )
        if (idx !== -1) {
          const current = target.messages[idx]
          target.messages[idx] = {
            ...current,
            ...saved,
            client_id: clientKey,
            status: saved.status ?? 'delivered',
          }
        }
      }
      return saved
    } catch (err) {
      const idx = target?.messages?.findIndex((m) => m.client_id === clientKey || m.id === clientKey)
      if (idx !== undefined && idx !== -1) {
        target.messages[idx].status = 'failed'
      }
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

  const processedMessageIds = new Set()

  const a$handleIncomingBroadcast = (data) => {
    if (!data?.message) return false

    const { message: msg, conversation: convSummary } = data
    const convId = msg.conversation_id

    // Deduplikasi pemrosesan event siaran agar tidak dobel hitung
    const eventKey = `${msg.id || msg.whatsapp_message_id || ''}-${msg.sent_at || ''}-${msg.text || ''}`
    const isAlreadyProcessed = processedMessageIds.has(eventKey)
    if (!isAlreadyProcessed) {
      processedMessageIds.add(eventKey)
      if (processedMessageIds.size > 200) {
        const first = processedMessageIds.values().next().value
        processedMessageIds.delete(first)
      }
    }

    let isNewBubble = false

    // 1. Update daftar kontak obrolan di sidebar
    let targetConv = conversations.value.find((c) => c.id === convId)
    if (targetConv) {
      targetConv.lastMessage = msg.text
      targetConv.last_message = msg.text
      targetConv.lastMessageTime = msg.sent_at || msg.time
      targetConv.last_message_time = msg.sent_at || msg.time

      // Jika obrolan ini SEDANG DIBUKA di layar chat utama, unread count selalu 0 (karena user sedang membaca)
      if (activeId.value === convId) {
        targetConv.unread_count = 0
        targetConv.unreadCount = 0
      } else if (!msg.is_from_me && !isAlreadyProcessed) {
        // Jika dari customer dan obrolan TIDAK sedang dibuka: gunakan nilai akurat dari backend
        if (typeof convSummary?.unread_count === 'number') {
          targetConv.unread_count = convSummary.unread_count
          targetConv.unreadCount = convSummary.unread_count
        } else {
          targetConv.unread_count = (targetConv.unread_count || 0) + 1
          targetConv.unreadCount = targetConv.unread_count
        }
      }
    } else if (convSummary) {
      // Obrolan baru yang belum ada di sidebar
      targetConv = {
        ...convSummary,
        messages: [],
      }
      conversations.value.unshift(targetConv)
    }

    // 2. Jika obrolan ini sedang dibuka di layar chat utama
    if (activeId.value === convId && targetConv) {
      if (!Array.isArray(targetConv.messages)) {
        targetConv.messages = []
      }

      // Cari apakah pesan sudah ada (misal dari optimistic bubble 'temp-' atau duplicate ID)
      const existingIdx = targetConv.messages.findIndex(
        (m) =>
          m.id === msg.id ||
          (msg.whatsapp_message_id && m.whatsapp_message_id === msg.whatsapp_message_id) ||
          ((String(m.id).startsWith('temp-') || String(m.client_id).startsWith('temp-')) &&
            m.text === msg.text &&
            m.is_from_me === msg.is_from_me)
      )

      if (existingIdx !== -1) {
        // Update pesan yang sudah ada dengan ID resmi dan status dari server (pertahankan client_id stabil)
        const current = targetConv.messages[existingIdx]
        targetConv.messages[existingIdx] = {
          ...current,
          ...msg,
          client_id: current.client_id || msg.id,
          status: msg.status || 'delivered',
        }
      } else {
        // Bubble baru masuk, push ke riwayat pesan
        targetConv.messages.push({
          ...msg,
          client_id: msg.id,
        })
        isNewBubble = true
      }
    }

    return isNewBubble
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
    a$handleIncomingBroadcast,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useConversationStore, import.meta.hot))
}
