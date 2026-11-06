// src/services/app/staff.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.staff

/**
 * Service function to retrieve station staff list with filters and KPI metrics.
 * @param {object} params - { search, role, station_name, is_on_duty, is_active, per_page }
 */
export const list = async (params = {}) => await api.get(url, { params })

/**
 * Service function to get single staff details by ID.
 * @param {string} id - Staff ULID
 */
export const show = async (id) => await api.get(`${url}/${id}`)

/**
 * Service function to create a new station staff member.
 * @param {object} data - { name, phone, role, station_name, is_active, is_on_duty, notes }
 */
export const create = async (data) => await api.post(url, data)

/**
 * Service function to update an existing station staff member.
 * @param {string} id - Staff ULID
 * @param {object} data - updated fields
 */
export const update = async (id, data) => await api.put(`${url}/${id}`, data)

/**
 * Service function to delete a station staff member.
 * @param {string} id - Staff ULID
 */
export const destroy = async (id) => await api.delete(`${url}/${id}`)

/**
 * Service function to toggle on-duty / off-duty status of a staff member.
 * @param {string} id - Staff ULID
 */
export const toggleDuty = async (id) => await api.patch(`${url}/${id}/toggle-duty`)

/**
 * Service function to retrieve active emergency alerts for dashboard monitoring.
 */
export const getActiveAlerts = async () => await api.get(`${endpoint.alerts}/active`)

/**
 * Service function to list all emergency alerts with filters.
 */
export const listAlerts = async (params = {}) => await api.get(endpoint.alerts, { params })

/**
 * Service function to acknowledge an active emergency alert.
 * @param {string} id - Alert ULID
 * @param {string} [acknowledgedBy='Admin']
 */
export const acknowledgeAlert = async (id, acknowledgedBy = 'Admin') =>
  await api.post(`${endpoint.alerts}/${id}/acknowledge`, { acknowledged_by: acknowledgedBy })

