// src/services/app/conversation.service.js

/**
 * Data dummy percakapan realistis
 */
const dummyConversations = [
  {
    id: 'conv-001',
    customer: {
      id: 101,
      name: 'Budi Santoso',
      phone: '6281234567890',
      meterNo: '54210988231',
      segment: 'R1T / 1300 VA',
      category: 'Prabayar',
      status: 'Active',
    },
    unreadCount: 1,
    status: 'unread', // all, unread, escalated
    handler: 'ai',
    lastMessage: 'Baik Bapak Budi, mohon pastikan 20 digit kode token sudah diinput dengan benar pada keypad meteran.',
    lastMessageTime: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
    messages: [
      {
        id: 1,
        sender: 'customer',
        text: 'Selamat pagi admin, saya baru beli token 100rb di minimarket sekitar 30 menit lalu tapi pas diinput di meteran selalu muncul tulisan GAGAL. Ini nomor meter saya 54210988231.',
        time: new Date(Date.now() - 15 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 2,
        sender: 'ai',
        text: 'Halo Bapak Budi Santoso, terima kasih telah menghubungi Customer Intelligence. Kami sedang memverifikasi transaksi token untuk ID Meter 54210988231.',
        time: new Date(Date.now() - 14 * 60 * 1000).toISOString(),
        status: 'read',
        intent: 'Verifikasi Token',
      },
      {
        id: 3,
        sender: 'ai',
        text: 'Data transaksi terdeteksi SUKSES pada pukul 09.30 WIB dengan nomor token: 4521-8890-1234-7789-0123 (Total 72.8 kWh). Apakah meteran Bapak menunjukkan pesan error spesifik seperti PERIKSA?',
        time: new Date(Date.now() - 12 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 4,
        sender: 'customer',
        text: 'Tidak ada tulisan PERIKSA, hanya tulisan GAGAL setelah saya tekan enter.',
        time: new Date(Date.now() - 8 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 5,
        sender: 'ai',
        text: 'Baik Bapak Budi, mohon pastikan 20 digit kode token sudah diinput dengan benar pada keypad meteran. Jika masih gagal, kami dapat membuatkan tiket pemeriksaan teknis ke petugas lapangan terdekat.',
        time: new Date(Date.now() - 5 * 60 * 1000).toISOString(),
        status: 'delivered',
        intent: 'Panduan Token & Eskalasi',
      },
    ],
  },
  {
    id: 'conv-002',
    customer: {
      id: 102,
      name: 'Siti Rahmawati',
      phone: '6285712345678',
      meterNo: '32109876543',
      segment: 'R1 / 900 VA',
      category: 'Pascabayar',
      status: 'Active',
    },
    unreadCount: 0,
    status: 'all',
    handler: 'ai',
    lastMessage: 'Estimasi biaya penyambungan tambah daya dari 900 VA ke 2.200 VA adalah sebesar Rp 1.218.000 (belum termasuk penyesuaian UJL).',
    lastMessageTime: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
    messages: [
      {
        id: 1,
        sender: 'customer',
        text: 'Halo, saya mau tanya simulasi biaya kalau mau tambah daya rumah dari 900 VA subsidi ke 2.200 VA kena berapa ya?',
        time: new Date(Date.now() - 60 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 2,
        sender: 'ai',
        text: 'Halo Ibu Siti Rahmawati, berikut rincian simulasi perubahan daya untuk ID Pelanggan 32109876543:',
        time: new Date(Date.now() - 58 * 60 * 1000).toISOString(),
        status: 'read',
        intent: 'Simulasi Tambah Daya',
      },
      {
        id: 3,
        sender: 'ai',
        text: 'Estimasi biaya penyambungan tambah daya dari 900 VA ke 2.200 VA adalah sebesar Rp 1.218.000 (belum termasuk penyesuaian UJL). Pengajuan dapat langsung diproses melalui portal ini atau aplikasi resmi.',
        time: new Date(Date.now() - 45 * 60 * 1000).toISOString(),
        status: 'read',
      },
    ],
  },
  {
    id: 'conv-003',
    customer: {
      id: 103,
      name: 'Ahmad Fauzi',
      phone: '6281398765432',
      meterNo: '51987654321',
      segment: 'R1 / 2200 VA',
      category: 'Prabayar',
      status: 'Active',
    },
    unreadCount: 3,
    status: 'escalated',
    handler: 'human',
    lastMessage: 'Listrik di satu komplek Jl. Melati Blok C padam total sejak jam 10 tadi, tolong segera dicek!',
    lastMessageTime: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
    messages: [
      {
        id: 1,
        sender: 'customer',
        text: 'Lapor min, ada pemadaman listrik tiba-tiba di Perumahan Griya Indah.',
        time: new Date(Date.now() - 25 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 2,
        sender: 'ai',
        text: 'Terima kasih atas laporannya Bapak Ahmad Fauzi. Boleh diinformasikan alamat lengkap serta apakah tetangga sekitar juga mengalami hal yang sama?',
        time: new Date(Date.now() - 22 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 3,
        sender: 'customer',
        text: 'Listrik di satu komplek Jl. Melati Blok C padam total sejak jam 10 tadi, tolong segera dicek!',
        time: new Date(Date.now() - 10 * 60 * 1000).toISOString(),
        status: 'read',
        intent: 'Laporan Gangguan Meluas',
      },
    ],
  },
  {
    id: 'conv-004',
    customer: {
      id: 104,
      name: 'Dewi Anggraeni',
      phone: '6281987654321',
      meterNo: '52876543210',
      segment: 'B1 / 4400 VA',
      category: 'Bisnis Pascabayar',
      status: 'Active',
    },
    unreadCount: 0,
    status: 'all',
    handler: 'ai',
    lastMessage: 'Tagihan listrik bulan berjalan untuk ID 52876543210 tercatat sebesar Rp 2.450.000 dengan status SUDAH LUNAS.',
    lastMessageTime: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(),
    messages: [
      {
        id: 1,
        sender: 'customer',
        text: 'Selamat siang, saya mau konfirmasi status tagihan listrik ruko saya untuk bulan ini apakah sudah tercatat lunas?',
        time: new Date(Date.now() - 190 * 60 * 1000).toISOString(),
        status: 'read',
      },
      {
        id: 2,
        sender: 'ai',
        text: 'Selamat siang Ibu Dewi Anggraeni. Tagihan listrik bulan berjalan untuk ID 52876543210 tercatat sebesar Rp 2.450.000 dengan status SUDAH LUNAS pada tanggal 10 Oktober 2026. Terima kasih atas ketepatan waktu pembayarannya.',
        time: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(),
        status: 'read',
        intent: 'Cek Status Tagihan',
      },
    ],
  },
]

/**
 * Service function to retrieve conversations list
 */
export const list = async () => {
  // Simulasi fetch async
  return {
    data: dummyConversations,
    title: 'Daftar Percakapan',
    status: true,
  }
}

/**
 * Service function to retrieve single conversation detail
 */
export const show = async (id) => {
  const conv = dummyConversations.find((c) => c.id === id)
  if (!conv) {
    throw new Error('Percakapan tidak ditemukan')
  }
  return {
    data: conv,
    title: 'Detail Percakapan',
    status: true,
  }
}
