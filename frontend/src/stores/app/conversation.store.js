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

  // ─── Getters ─────────────────────────────────────────
  const g$conversations = computed(() => conversations.value)

  const g$activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeId.value) || null
  )

  const g$filteredList = computed(() => {
    let result = conversations.value

    // Filter status tab
    if (filterStatus.value === 'unread') {
      result = result.filter((c) => c.unreadCount > 0)
    } else if (filterStatus.value === 'escalated') {
      result = result.filter((c) => c.status === 'escalated' || c.handler === 'human')
    }

    // Filter keyword pencarian
    const q = searchQuery.value.trim().toLowerCase()
    if (q) {
      result = result.filter(
        (c) =>
          c.customer.name.toLowerCase().includes(q) ||
          c.customer.phone.includes(q) ||
          c.customer.meterNo.includes(q) ||
          c.lastMessage.toLowerCase().includes(q)
      )
    }

    return result
  })

  const g$loading = computed(() => loading.value)
  const g$activeId = computed(() => activeId.value)
  const g$filterStatus = computed(() => filterStatus.value)
  const g$searchQuery = computed(() => searchQuery.value)

  // ─── Actions ─────────────────────────────────────────
  const a$fetchList = async () => {
    loading.value = true
    try {
      const { data } = await s$conversation.list()
      conversations.value = data

      // Otomatis pilih item pertama jika belum ada yang aktif
      if (!activeId.value && data.length > 0) {
        activeId.value = data[0].id
      }
    } catch (e) {
      throw e?.message ?? e
    } finally {
      loading.value = false
    }
  }

  const a$select = (id) => {
    activeId.value = id
    // Tandai pesan sudah dibaca saat dibuka
    const target = conversations.value.find((c) => c.id === id)
    if (target && target.unreadCount > 0) {
      target.unreadCount = 0
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
    // getters
    g$conversations,
    g$activeConversation,
    g$filteredList,
    g$loading,
    g$activeId,
    g$filterStatus,
    g$searchQuery,
    // actions
    a$fetchList,
    a$select,
    a$search,
    a$filterByStatus,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useConversationStore, import.meta.hot))
}
