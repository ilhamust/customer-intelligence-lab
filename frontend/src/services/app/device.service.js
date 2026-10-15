// src/services/app/device.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.whatsapp

/**
 * Fetch all registered WhatsApp devices with their status.
 */
export const list = async () => await api.get(`${url}/devices`)

/**
 * Get device status.
 */
export const status = async (deviceId) =>
  await api.get(`${url}/status`, { params: deviceId ? { device_id: deviceId } : {} })

/**
 * Register a new WhatsApp gateway device.
 * @param {{ device_id: string, name: string }} body
 */
export const register = async (body) => await api.post(`${url}/devices`, body)

/**
 * Request dynamic login QR code in-memory Base64 data URL.
 * @param {string} deviceId
 */
export const loginQr = async (deviceId) =>
  await api.post(`${url}/login-qr`, {}, { params: deviceId ? { device_id: deviceId } : {} })

/**
 * Request 8-digit pairing code for login using phone number.
 * @param {{ device_id?: string, phone: string }} body
 */
export const loginCode = async (body) => await api.post(`${url}/login-code`, body)

/**
 * Terminate and logout WhatsApp session.
 * @param {string} deviceId
 */
export const logout = async (deviceId) =>
  await api.post(`${url}/logout`, {}, { params: deviceId ? { device_id: deviceId } : {} })

/**
 * Reconnect WhatsApp gateway device.
 * @param {string} deviceId
 */
export const reconnect = async (deviceId) =>
  await api.post(`${url}/reconnect`, {}, { params: deviceId ? { device_id: deviceId } : {} })

/**
 * Delete WhatsApp device from gateway and database.
 * @param {string} deviceId
 */
export const destroy = async (deviceId) => await api.delete(`${url}/devices/${deviceId}`)
