// src/utils/constant.js
export const BASE_URL = import.meta.env.VITE_BASEURL ?? 'http://localhost:3001'
export const AUTH_TOKEN_NAME = 'jarvis_token'

export const endpoint = {
  auth: '/api/v1',
  customers: '/api/v1/customers',
  tickets: '/api/v1/tickets',
  alerts: '/api/v1/alerts',
  conversations: '/api/v1/conversations',
  knowledge: '/api/v1/knowledge-articles',
  dashboard: '/api/v1/dashboard',
  simulator: '/api/v1/sim/messages',
  whatsapp: '/api/v1/whatsapp',
}
