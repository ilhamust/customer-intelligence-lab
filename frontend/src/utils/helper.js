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
 * Format waktu ala WhatsApp (misal: "09:42", "Kemarin", "14 Okt")
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatRelativeTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return timestamp

  const now = new Date()
  const isToday = date.toDateString() === now.toDateString()

  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  const isYesterday = date.toDateString() === yesterday.toDateString()

  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const timeStr = `${hours}:${minutes}`

  if (isToday) return timeStr
  if (isYesterday) return 'Yesterday'

  // If same year
  const day = date.getDate()
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]

  if (date.getFullYear() === now.getFullYear()) {
    return `${day} ${month}`
  }

  return `${day}/${date.getMonth() + 1}/${String(date.getFullYear()).slice(-2)}`
}

/**
 * Format jam dan menit "HH:mm"
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatTime = (timestamp) => {
  if (!timestamp) return ''
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return timestamp
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')
  return `${hours}:${minutes}`
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
 * Format tanggal dan waktu lengkap (misal: "Oct 15, 2026, 2:58 PM")
 * @param {string|Date} timestamp
 * @returns {string}
 */
export const formatDateTime = (timestamp) => {
  if (!timestamp) return '-'
  const date = new Date(timestamp)
  if (isNaN(date.getTime())) return String(timestamp)

  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  const month = months[date.getMonth()]
  const day = date.getDate()
  const year = date.getFullYear()

  let hours = date.getHours()
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const ampm = hours >= 12 ? 'PM' : 'AM'
  hours = hours % 12
  hours = hours ? hours : 12

  return `${month} ${day}, ${year}, ${hours}:${minutes} ${ampm}`
}

