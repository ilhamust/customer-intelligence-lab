// src/plugins/axios.js
import axios from 'axios'
import { del, get } from '@/plugins/idb'
import { AUTH_TOKEN_NAME, BASE_URL as baseURL } from '@/utils/constant'

const api = axios.create({
  baseURL,
  headers: { Accept: 'application/json' },
})

// ─── Request ─────────────────────────────────────────────
api.interceptors.request.use(async (config) => {
  const token = await get(AUTH_TOKEN_NAME)
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

// ─── Response ────────────────────────────────────────────
const toMessage = (error) => {
  const data = error.response?.data
  return data?.errors?.[0]?.details ?? data?.title ?? error.message
}

api.interceptors.response.use(
  (response) => response.data,
  async (error) => {
    const code = error.response?.status
    const isLogin = error.config?.url?.endsWith('/login')

    // 401: token tidak valid atau kedaluwarsa. Hanya 401 yang me-logout.
    if (code === 401 && !isLogin) {
      await del(AUTH_TOKEN_NAME)
      window.dispatchEvent(new Event('auth:unauthorized'))
    }

    // 403: sudah login tetapi tidak berhak. JANGAN logout, cukup tampilkan pesan.
    return Promise.reject({
      code,
      message: toMessage(error),
      errors: error.response?.data?.errors ?? [],
    })
  }
)

export default {
  install: (app) => {
    app.config.globalProperties.$api = api
    app.provide('api', api)
  },
}

export { api }
