// src/services/app/charging.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = `${endpoint.charging}/transactions`

/**
 * Retrieve EV charging transactions list with filters and KPI metrics.
 * @param {object} params - { search, station_code, status, customer_id, date_from, date_to, per_page, page }
 */
export const listTransactions = async (params = {}) => await api.get(url, { params })

