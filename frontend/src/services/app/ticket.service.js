// src/services/app/ticket.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.tickets

/**
 * Service function to retrieve support tickets list with optional filters.
 * @param {object} params - { status, category, search, page, per_page }
 */
export const list = async (params = {}) => await api.get(url, { params })

/**
 * Service function to retrieve real-time CS service status (is_busy, queue counts, active_ticket).
 */
export const getCsStatus = async () => await api.get(`${url}/status`)

/**
 * Service function to start serving a ticket (change status to in_progress, trigger WhatsApp greeting).
 * @param {string} id - Ticket ULID
 * @param {string|null} [adminName=null] - Optional CS display name
 */
export const serve = async (id, adminName = null) =>
  await api.post(`${url}/${id}/serve`, adminName ? { admin_name: adminName } : {})

/**
 * Service function to resolve an active ticket (change status to resolved, trigger WhatsApp closing message).
 * @param {string} id - Ticket ULID
 * @param {string|null} [resolutionNotes=null] - Optional notes describing the resolution
 */
export const resolve = async (id, resolutionNotes = null) =>
  await api.post(`${url}/${id}/resolve`, resolutionNotes ? { resolution_notes: resolutionNotes } : {})
