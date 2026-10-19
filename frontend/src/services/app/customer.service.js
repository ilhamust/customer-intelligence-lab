// src/services/app/customer.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.customers

export const list = async (params) => await api.get(url, { params })
export const show = async (id) => await api.get(`${url}/${id}`)
export const points = async (id) => await api.get(`${url}/${id}/points`)
export const ledger = async (id, params) => await api.get(`${url}/${id}/ledger`, { params })
