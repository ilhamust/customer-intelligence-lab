// src/services/app/conversation.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.conversations

/**
 * Service function to retrieve conversations list with optional filters.
 * @param {object} params - { search, status, device_id }
 */
export const list = async (params = {}) => await api.get(url, { params })

/**
 * Service function to retrieve single conversation detail.
 * @param {string} id
 */
export const show = async (id) => await api.get(`${url}/${id}`)

/**
 * Service function to retrieve messages for a conversation.
 * @param {string} id
 * @param {object} params - { limit }
 */
export const getMessages = async (id, params = {}) =>
  await api.get(`${url}/${id}/messages`, { params })

/**
 * Service function to send an outbound text message in a conversation.
 * @param {string} id
 * @param {string} text
 * @param {string} [senderType='human']
 */
export const send = async (id, text, senderType = 'human') =>
  await api.post(`${url}/${id}/messages`, { text, sender_type: senderType })

/**
 * Service function to synchronize conversations and message history from WhatsApp Gateway.
 * @param {string|null} [deviceId=null]
 */
export const sync = async (deviceId = null) =>
  await api.post(`${url}/sync`, {}, { params: deviceId ? { device_id: deviceId } : {} })

/**
 * Service function to notify typing presence and trigger operator auto-takeover.
 * @param {string} id
 * @param {'start'|'stop'} [action='start']
 */
export const sendTyping = async (id, action = 'start') =>
  await api.post(`${url}/${id}/typing`, { action })

/**
 * Service function to manually update conversation handler mode ('ai' | 'human').
 * @param {string} id
 * @param {'ai'|'human'} handler
 */
export const updateHandler = async (id, handler) =>
  await api.patch(`${url}/${id}/handler`, { handler })

