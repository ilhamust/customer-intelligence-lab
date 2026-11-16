// src/utils/helper.js

/**
 * Mendapatkan inisial nama maksimal 2 karakter kapital
 * @param {string} name
 * @returns {string}
 */
export const getInitials = (name) => {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
}

/**
 * Format waktu ala WhatsApp berbasis zona WIB (misal: "09:42", "Kemarin", "14 Okt")
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatRelativeTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return timestamp

  const now = new Date()
  const toWibDateString = (d) =>
    new Intl.DateTimeFormat('id-ID', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(d)

  const isToday = toWibDateString(date) === toWibDateString(now)

  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  const isYesterday = toWibDateString(date) === toWibDateString(yesterday)

  const timeStr = formatTime(date)

  if (isToday) return timeStr
  if (isYesterday) return 'Kemarin'

  const yearFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
  })
  const sameYear = yearFormatter.format(date) === yearFormatter.format(now)

  if (sameYear) {
    return new Intl.DateTimeFormat('id-ID', {
      timeZone: 'Asia/Jakarta',
      day: 'numeric',
      month: 'short',
    }).format(date)
  }

  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'numeric',
    year: '2-digit',
  }).format(date)
}

/**
 * Format jam dan menit WIB "HH:mm"
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return timestamp

  return new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date).replace('.', ':')
}

/**
 * Format nomor telepon WhatsApp (+62 812-3456-7890)
 * @param {string} phone
 * @returns {string}
 */
export const formatPhone = (phone) => {
  if (!phone) return ''
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.startsWith('62')) {
    return `+62 ${cleaned.slice(2, 5)}-${cleaned.slice(5, 9)}-${cleaned.slice(9)}`
  }
  return phone
}

/**
 * Format tanggal dan waktu lengkap berbasis WIB (misal: "16 Nov 2026, 17:09 WIB")
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatDateTime = (timestamp) => {
  if (!timestamp) return '-'
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return String(timestamp)

  const formatted = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).format(date)

  return `${formatted.replace('.', ':')} WIB`
}

/**
 * Format nominal Rupiah (misal: "Rp 70.000")
 * @param {number|string} amount
 * @returns {string}
 */
export const formatRupiah = (amount) => {
  if (amount === null || amount === undefined || isNaN(Number(amount))) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(Number(amount))
}

/**
 * Format angka numerik dengan desimal id-ID (misal: "20,50")
 * @param {number|string} num
 * @param {number} decimals
 * @returns {string}
 */
export const formatNumber = (num, decimals = 2) => {
  if (num === null || num === undefined || isNaN(Number(num))) return '0'
  return new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: decimals,
  }).format(Number(num))
}


