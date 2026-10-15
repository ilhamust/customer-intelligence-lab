# Frontend Context: Jarvis (Vue 3)

File ini berisi pola dan konvensi frontend Jarvis. Seluruh UI adalah **satu aplikasi Back Office (dashboard)** yang diakses oleh admin. Tidak ada halaman terpisah untuk customer. Contoh kode sudah disesuaikan dengan autentikasi Sanctum dan format response backend. Jangan menambahkan library atau pola di luar yang tercantum di sini kecuali benar-benar dibutuhkan.

---

## 1. Keputusan

| Hal | Keputusan |
|---|---|
| Stack | Vue 3 · Vite · Pinia · Vue Router · Tailwind CSS v4 · DaisyUI 5 · Axios |
| Library tambahan | `idb-keyval` (penyimpanan token), `vue-toastification` (notifikasi), `dompurify` (hanya jika ada HTML dari pengguna) |
| Bentuk aplikasi | Satu Back Office. Semua fitur adalah menu di dalam dashboard. |
| Pengakses | Admin, melalui login email dan password |
| Auth | Token Sanctum (token biasa, **bukan JWT**). Disimpan di IndexedDB. Data user diambil dari `GET /me`, tidak di-decode di klien. |
| Refresh token | Tidak ada. Saat 401, hapus token lalu arahkan ke login. |
| Role | `admin`. Pengecekan di frontend hanya untuk tampilan, izin sebenarnya dicek backend. |
| Base URL | Satu variabel: `VITE_BASEURL`. Tidak ada rahasia lain di variabel `VITE_*`. |
| Tailwind | Tailwind v4: tanpa `tailwind.config.js`, semua konfigurasi di `app.css` |
| Bahasa / Language | **Full English (`en-US`)**. Seluruh UI, label tombol, teks modal, placeholder, tooltip, empty state, notifikasi toast, badge, dan pesan konfirmasi dialog wajib konsisten menggunakan Bahasa Inggris. Tidak diperkenankan bercampur dengan Bahasa Indonesia. |

### Menu Back Office

| Menu | Path | Fungsi |
|---|---|---|
| Dashboard | `/app` | Ringkasan angka: customer, kWh, sesi hari ini, alert aktif |
| Customers | `/app/customers` | Daftar customer. Detail (`/app/customers/:id`) berisi profil 360°, saldo poin, dan riwayat poin |
| Tickets | `/app/tickets` | Tiket yang perlu ditangani manusia |
| Alerts | `/app/alerts` | Alert urgent dan alert inaktivitas, dengan status |
| Conversations | `/app/conversations` | Riwayat percakapan WhatsApp (hanya baca) |
| Knowledge | `/app/knowledge` | Kelola artikel FAQ yang dibaca AI |
| Simulator Chat | `/app/simulator` | Menguji bot dan AI tanpa mengirim WhatsApp sungguhan. Hanya tampil saat mode development. |

---

## 2. Struktur folder `src/`

```
src/
├── App.vue                    # root: init auth, listener 'auth:unauthorized'
├── app.css                    # Tailwind v4 + tema DaisyUI + utility kustom
├── main.js                    # bootstrap: Pinia, router, plugin
│
├── components/                # komponen dasar (didaftarkan global)
│   ├── BaseBadge.vue
│   ├── BaseButton.vue
│   ├── BaseCard.vue
│   ├── BaseDialog.vue
│   ├── BaseEmpty.vue
│   ├── BaseInput.vue
│   ├── BaseLoading.vue
│   ├── BasePagination.vue
│   ├── BaseStat.vue           # kartu angka sederhana untuk dashboard
│   └── BaseTable.vue
│
├── layouts/
│   ├── app/                   # AppIndex.vue, AppNavbar.vue, AppSidebar.vue
│   └── auth/                  # AuthIndex.vue (minimal)
│
├── pages/
│   ├── NotFoundPage.vue
│   ├── ForbiddenPage.vue
│   ├── auth/LoginPage.vue
│   └── app/
│       ├── DashboardPage.vue
│       ├── customers/{CustomerListPage.vue, CustomerDetailPage.vue}
│       ├── tickets/TicketListPage.vue
│       ├── alerts/AlertListPage.vue
│       ├── conversations/ConversationListPage.vue
│       ├── knowledge/{KnowledgeListPage.vue, KnowledgeFormPage.vue}
│       └── simulator/SimulatorPage.vue
│
├── plugins/
│   ├── index.js               # re-export semua plugin
│   ├── axios.js               # klien HTTP (bagian 4)
│   ├── idb.js                 # get/set/del token (idb-keyval)
│   ├── notify.js              # toast (vue-toastification)
│   └── global.js              # registrasi komponen Base
│
├── router/
│   ├── index.js               # instance router + guard (bagian 5)
│   └── routes/{auth.route.js, app.route.js}
│
├── services/                  # 1 file = 1 domain endpoint
│   ├── auth.service.js
│   └── app/{dashboard, customer, ticket, alert, conversation, knowledge, simulator}.service.js
│
├── stores/                    # Pinia
│   ├── auth.store.js
│   └── app/{dashboard, customer, ticket, alert, conversation, knowledge, simulator}.store.js
│
└── utils/
    ├── constant.js            # BASE_URL, AUTH_TOKEN_NAME, endpoint map
    └── helper.js              # fungsi murni (format tanggal, angka, dll.)
```

### Konvensi penamaan

| Kategori | Pola | Contoh |
|---|---|---|
| Page | `PascalCase` + `Page` | `CustomerListPage.vue` |
| Layout | `PascalCase` + `Index` | `AppIndex.vue` |
| Komponen dasar | prefix `Base` | `BaseButton.vue` |
| Store | `kebab-case` + `.store.js` | `ticket.store.js` |
| Service | `kebab-case` + `.service.js` | `customer.service.js` |
| Route | `kebab-case` + `.route.js` | `app.route.js` |

---

## 3. Pinia store

Pola: Composition API (`setup`), prefix `g$` untuk getter dan `a$` untuk action. Di komponen: `const p$auth = useAuthStore()`. Service diimpor sebagai `s$...`. Error di-catch lalu dilempar ulang sebagai `e?.message ?? e`.

### `stores/auth.store.js`

```js
import { ref, computed } from 'vue'
import { defineStore, acceptHMRUpdate } from 'pinia'

import { get, set, del } from '@/plugins/idb'
import { AUTH_TOKEN_NAME } from '@/utils/constant'
import * as s$auth from '@/services/auth.service'

export const useAuthStore = defineStore('auth', () => {
  // ─── State ───────────────────────────────────────────
  const ready = ref(false)
  const user = ref({ id: null, name: null, role: null })

  // ─── Getters ─────────────────────────────────────────
  const g$user = computed(() => user.value)
  const g$isAdmin = computed(() => user.value.role === 'admin')

  // ─── Helpers ─────────────────────────────────────────
  const setUser = (u) =>
    Object.assign(user.value, { id: u?.id ?? null, name: u?.name ?? null, role: u?.role ?? null })

  // ─── Actions ─────────────────────────────────────────

  /** Dipanggil sekali saat bootstrap: pulihkan sesi dari token yang tersimpan */
  const a$init = async () => {
    if (ready.value) return
    try {
      const token = await get(AUTH_TOKEN_NAME)
      if (token) await a$user()
    } catch {
      /* token tidak valid: a$user sudah membersihkan state */
    } finally {
      ready.value = true
    }
  }

  /** Ambil data user dari backend (GET /me) */
  const a$user = async () => {
    try {
      const { data } = await s$auth.me()
      setUser(data)
    } catch (e) {
      setUser(null)
      await del(AUTH_TOKEN_NAME)
      throw e?.message ?? e
    }
  }

  /** @param {{ email: string, password: string }} body */
  const a$login = async (body) => {
    try {
      const { data, title } = await s$auth.login(body)
      await set(AUTH_TOKEN_NAME, data.token)
      setUser(data.user)
      return title
    } catch (e) {
      throw e?.message ?? e
    }
  }

  const a$logout = async () => {
    try {
      await s$auth.logout()
    } catch {
      /* tetap lanjut membersihkan sisi klien */
    } finally {
      await del(AUTH_TOKEN_NAME)
      setUser(null)
    }
    return 'Logout success'
  }

  /** Dipakai saat token ditolak backend (401) */
  const a$clear = async () => {
    await del(AUTH_TOKEN_NAME)
    setUser(null)
  }

  return { g$user, g$isAdmin, a$init, a$user, a$login, a$logout, a$clear }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
```

### Alur data

```
Komponen → p$store.a$action(body) → s$service.fn(body) → api (Axios) → Backend
Backend → Axios (unwrap response.data) → Service → Store (update ref) → Komponen
```

Setiap halaman daftar wajib punya tiga kondisi tampilan: loading (`BaseLoading`), kosong (`BaseEmpty`), dan error (toast lewat `notify.error`). Jangan membiarkan layar kosong tanpa penjelasan.

---

## 4. Klien Axios (`plugins/axios.js`)

Format response backend:
- Sukses: `{ data, title, status, code, meta }`
- Error: `{ errors: [ { title, details, code, status, meta } ] }`

Klien ini menempelkan Bearer token, mengembalikan `response.data` (unwrap), dan menormalkan error menjadi `{ code, message, errors }` sehingga store cukup melempar `e.message`.

```js
import axios from 'axios'
import { del, get } from '@/plugins/idb'
import { AUTH_TOKEN_NAME, BASE_URL as baseURL } from '@/utils/constant'

const api = axios.create({
  baseURL,
  headers: { Accept: 'application/json' }
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
      errors: error.response?.data?.errors ?? []
    })
  }
)

export default {
  install: (app) => {
    app.config.globalProperties.$api = api
    app.provide('api', api)
  }
}

export { api }
```

`App.vue` mendengarkan event `auth:unauthorized`, memanggil `p$auth.a$clear()`, lalu `router.replace({ name: 'Login' })`.

### Service layer

```js
// services/auth.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.auth

export const login  = async (body) => await api.post(`${url}/login`, body)
export const logout = async ()     => await api.post(`${url}/logout`)
export const me     = async ()     => await api.get(`${url}/me`)
```

```js
// services/app/customer.service.js
import { api } from '@/plugins'
import { endpoint } from '@/utils/constant'

const url = endpoint.customers

export const list   = async (params) => await api.get(url, { params })
export const show   = async (id)     => await api.get(`${url}/${id}`)
export const points = async (id)     => await api.get(`${url}/${id}/points`)
export const ledger = async (id, params) => await api.get(`${url}/${id}/ledger`, { params })
```

### `utils/constant.js`

```js
export const BASE_URL = import.meta.env.VITE_BASEURL ?? 'http://localhost:3001'
export const AUTH_TOKEN_NAME = 'jarvis_token'

export const endpoint = {
  auth:          '/api/v1',
  customers:     '/api/v1/customers',
  tickets:       '/api/v1/tickets',
  alerts:        '/api/v1/alerts',
  conversations: '/api/v1/conversations',
  knowledge:     '/api/v1/knowledge-articles',
  dashboard:     '/api/v1/dashboard',
  simulator:     '/api/v1/sim/messages'
}
```

Sesuaikan path dengan route backend yang sebenarnya.

---

## 5. Router dan guard

```js
// router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import routes from './routes'

const router = createRouter({ history: createWebHistory(), routes })

// Redirect hanya ke path internal (cegah open redirect)
export const safeRedirect = (path) =>
  typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') ? path : '/app'

router.beforeEach(async (to) => {
  const p$auth = useAuthStore()
  await p$auth.a$init()
  const user = p$auth.g$user

  const needAuth = to.matched.some(({ meta }) => meta?.auth)
  if (needAuth && !user.id) {
    return { name: 'Login', replace: true, query: { redirect: to.fullPath } }
  }

  // Pengecekan role di sini hanya untuk tampilan. Backend tetap memeriksa izin.
  const roles = to.matched.map(({ meta }) => meta?.roles).filter(Boolean).pop()
  if (roles && !roles.includes(user.role)) return { name: 'Forbidden' }

  if (to.name === 'Login' && user.id) return { name: 'App', replace: true }
})

export default router
```

Setelah login berhasil, arahkan dengan `router.replace(safeRedirect(route.query.redirect))`.

### Daftar route

| Path | Name | Auth | Role | Layout |
|---|---|---|---|---|
| `/` | | | | redirect ke `/app` |
| `/auth/login` | `Login` | Tidak | | `AuthIndex` |
| `/app` | `App` (Dashboard) | Ya | admin | `AppIndex` |
| `/app/customers` | `Customers` | Ya | admin | ↑ |
| `/app/customers/:id` | `CustomerDetail` | Ya | admin | ↑ |
| `/app/tickets` | `Tickets` | Ya | admin | ↑ |
| `/app/alerts` | `Alerts` | Ya | admin | ↑ |
| `/app/conversations` | `Conversations` | Ya | admin | ↑ |
| `/app/knowledge`, `/app/knowledge/:id?` | `Knowledge` | Ya | admin | ↑ |
| `/app/simulator` | `Simulator` | Ya | admin | ↑ (hanya didaftarkan saat `import.meta.env.DEV`) |
| `/forbidden` | `Forbidden` | Ya | | |
| `/:pathMatch(.*)*` | `NotFound` | Tidak | | |

`meta.auth` dan `meta.roles` dipasang sekali pada route induk `/app`, sehingga semua anak mewarisinya. Route simulator ditambahkan secara kondisional:

```js
...(import.meta.env.DEV
  ? [{ path: 'simulator', name: 'Simulator', component: () => import('@/pages/app/simulator/SimulatorPage.vue') }]
  : [])
```

---

## 6. Halaman Back Office

### 6.1 Dashboard

Kartu angka (`BaseStat`): total customer, total kWh, sesi hari ini, alert aktif. Tanpa library grafik di tahap awal.

### 6.2 Customers

- **Daftar:** pencarian dan paginasi.
- **Detail:** dibagi dalam tab.
  - *Profil 360°:* kendaraan, tipe driver, area, rute, kunjungan per minggu, jam dan stasiun favorit, status, risiko churn.
  - *Poin:* saldo poin dan tabel riwayat poin (ledger) dengan paginasi.
  - *Percakapan:* tautan ke riwayat chat customer tersebut.

### 6.3 Tickets dan Alerts

Daftar dengan filter status. Alert bisa diubah statusnya: baru, diterima, selesai. Alert urgent diberi warna `error`, perlu manusia `warning`.

### 6.4 Conversations

Baca riwayat percakapan per customer (hanya baca), termasuk jawaban AI.

### 6.5 Knowledge

CRUD artikel FAQ: judul, isi, dan stasiun (opsional). Perubahan langsung memengaruhi jawaban AI.

### 6.6 Simulator Chat (hanya development)

Tujuan: menguji bot dan AI tanpa mengirim pesan WhatsApp sungguhan.

- Input nomor telepon (atau pilih dari daftar customer uji), kolom pesan, dan daftar bubble masuk dan keluar.
- Kirim pesan: `POST` ke endpoint simulator. Balasan diambil dengan **polling** setiap 1,5 detik memakai parameter `after_id` (hanya pesan baru).
- Panel debug di samping: status percakapan (langkah registrasi), tool yang dipanggil AI beserta hasilnya, dan tingkat eskalasi. Isinya berasal dari `meta` pesan.
- Tampilkan alert urgent yang masuk sebagai panel terpisah.
- Hentikan polling saat komponen dilepas.

```js
let timer
const poll = async () => {
  const { data } = await s$sim.list({ phone: phone.value, after_id: lastId.value })
  if (data.length) {
    messages.value.push(...data)
    lastId.value = data.at(-1).id
  }
}
onMounted(() => { timer = setInterval(poll, 1500) })
onBeforeUnmount(() => clearInterval(timer))
```

---

## 7. Tailwind v4 dan DaisyUI 5

Tidak ada `tailwind.config.js`. Seluruh konfigurasi Tailwind v4 dan tema DaisyUI dilakukan di `app.css`.

Palet warna:
- **Mode Light (Default)**: Latar belakang putih/bersih, warna utama Cyan/Teal, aksen kuning, dan warna pendukung biru elektrik.
- **Mode Dark (Menyesuaikan)**: Penyesuaian skema gelap dengan latar belakang navy slate, kontras tinggi, dan aksen warna yang tetap konsisten dan nyaman di mata saat kondisi minim cahaya.

```css
@import 'tailwindcss';

@plugin 'daisyui' {
  logs: false;
}

/* ─── Light Theme ────────────────────────────────────────────── */
@plugin 'daisyui/theme' {
  name: 'light';
  default: true;
  prefersdark: false;
  color-scheme: 'light';

  /* Base Background */
  --color-base-100: oklch(100% 0 0);
  --color-base-200: oklch(97.5% 0.008 215);
  --color-base-300: oklch(92% 0.012 215);
  --color-base-content: oklch(22% 0.03 240);

  /* Brand Colors */
  --color-primary: oklch(63% 0.14 215);
  --color-primary-content: oklch(99% 0 0);
  --color-secondary: oklch(60% 0.16 235);
  --color-secondary-content: oklch(99% 0 0);
  --color-accent: oklch(85% 0.19 88);
  --color-accent-content: oklch(20% 0.04 88);
  --color-neutral: oklch(28% 0.03 240);
  --color-neutral-content: oklch(98% 0.005 240);

  /* Semantic */
  --color-info: oklch(68% 0.15 225);
  --color-success: oklch(72% 0.17 155);
  --color-warning: oklch(76% 0.16 75);
  --color-error: oklch(63% 0.22 25);

  --radius-selector: 0.5rem;
  --radius-field: 0.5rem;
  --radius-box: 0.5rem;
  --border: 1px;
}

/* ─── Dark Theme ─────────────────────────────────────────────── */
@plugin 'daisyui/theme' {
  name: 'dark';
  default: false;
  prefersdark: true;
  color-scheme: 'dark';

  /* Base Background */
  --color-base-100: oklch(22% 0.025 225);
  --color-base-200: oklch(18% 0.022 225);
  --color-base-300: oklch(27% 0.03 225);
  --color-base-content: oklch(96% 0.01 215);

  /* Brand Colors */
  --color-primary: oklch(70% 0.14 215);
  --color-primary-content: oklch(18% 0.04 215);
  --color-secondary: oklch(68% 0.15 235);
  --color-secondary-content: oklch(18% 0.04 235);
  --color-accent: oklch(84% 0.18 88);
  --color-accent-content: oklch(20% 0.04 88);
  --color-neutral: oklch(16% 0.02 225);
  --color-neutral-content: oklch(92% 0.01 225);

  /* Semantic */
  --color-info: oklch(74% 0.15 225);
  --color-success: oklch(75% 0.17 155);
  --color-warning: oklch(80% 0.17 75);
  --color-error: oklch(70% 0.20 25);

  --radius-selector: 0.5rem;
  --radius-field: 0.5rem;
  --radius-box: 0.5rem;
  --border: 1px;
}

@utility input-block { width: 100%; }
```

Gunakan token warna DaisyUI (`bg-base-100`, `text-primary`, `btn-primary`, `badge-warning`) alih-alih warna hardcode, supaya tema bisa diganti dari satu tempat.

### 7.1 Aturan Desain UI: Larangan Efek Neon, Transparansi, & Outline

Untuk menjaga tampilan Back Office tetap elegan, profesional, dan bersih (bukan tampilan neon atau berantakan):

1. **Dilarang Keras Memakai Efek Neon & Background Transparan (`/10`, `/15`, `/20`, dll.)**:
   - Dilarang menggunakan kombinasi warna brand transparan seperti `bg-primary/5`, `bg-primary/8`, `bg-primary/10`, `bg-primary/15`, `bg-primary/20`.
   - Warna transparan semacam ini menghasilkan visual "neon kusam" dan terlihat tidak solid.
   - Komponen aktif, bubble chat, dan elemen brand harus menggunakan warna pekat solid (`bg-primary text-primary-content`) atau latar netral solid (`bg-base-200`, `bg-base-100`, `bg-base-300`).

2. **Dilarang Memakai Outline / Border Transparan**:
   - Dilarang memakai border outline tipis/transparan seperti `border-primary/20` atau `border-base-300/40`.
   - Gunakan garis pembatas solid yang tegas (`border-base-300`) untuk pemisah kolom/layout, atau **tanpa border sama sekali** untuk bubble chat.

3. **Brand Logo Sidebar Wajib Murni Tipografi (DILARANG Menambahkan Ikon)**:
   - **Jangan menambahkan ikon, svg, atau emblem apapun** di samping teks brand header sidebar.
   - Brand logo di sidebar adalah **murni teks tipografi tebal**:
     ```html
     <span class="text-[15px] font-black text-base-content tracking-tight truncate">Intelligence Lab</span>
     ```

4. **Kuning (`accent`) HANYA untuk Aksen Tombol Aksi**:
   - Warna kuning tidak boleh digunakan sebagai latar navigasi, kartu, atau elemen umum. Kuning hanya diperuntukkan sebagai aksen tombol interaktif utama (misal: tombol Kirim pesan).

5. **Bubble Chat Ala WhatsApp**:
   - Kedua bubble (pengirim dan penerima) wajib menggunakan warna pekat solid dan **tanpa outline/border sama sekali**.
   - Pengirim: Solid `bg-primary text-primary-content`.
   - Penerima: Solid `bg-base-100 dark:bg-base-300 text-base-content`.

6. **Dilarang Mengubah Warna Teks Profil / Teks Statis Menjadi Tosca Saat Hover**:
   - **Dilarang keras** menambahkan efek hover perubahan warna tosca/primary (`hover:text-primary`, `group-hover:text-primary`) pada teks profil pengguna (nama 'Admin', avatar, role), judul halaman, header, atau teks label statis.
   - Profil pengguna dan teks statis harus tetap menggunakan warna alaminya (`text-base-content` atau `text-base-content/60`) tanpa berubah warna saat dilewati kursor.
   - Warna tosca/primary **hanya** untuk menu navigasi yang sedang berstatus aktif (kapsul solid) atau tautan/tombol aksi primer yang memang dirancang untuk berpindah rute/tindakan.

### 7.2 Standar Desain UI: Acuan Desain Flat SaaS Modern (Mode Light & Arsitektur)

Gaya visual yang digunakan pada aplikasi ini mengacu pada standar **Modern Flat SaaS Dashboard**. Seluruh pembuatan halaman dan komponen baru (Dashboard, Customers, Tickets, Alerts, Knowledge) **wajib mengikuti aturan ini secara konsisten**:

#### 1. Arsitektur Layout: Floating Rounded Cards di atas Kanvas Terpisah
- **Kanvas Luar / Latar Belakang Aplikasi**: Menggunakan `bg-base-200`.
- **Struktur Halaman Konten**:
  - Halaman konten **wajib dibungkus dengan padding celah**: `class="h-full p-4 flex gap-4 bg-base-200 overflow-hidden"`.
  - **Dilarang keras membuat area konten menempel langsung (wall-to-wall)** ke sidebar dan topbar tanpa celah `bg-base-200`. Jarak visual 16px (`p-4 gap-4`) ini menjaga agar sidebar, navbar, dan konten memiliki pemisahan arsitektur yang jelas dan tidak terlihat "menyatu".
  - Seluruh panel konten (tabel, daftar chat, form, kartu analitik) wajib berwujud **Floating Rounded Cards**:
    ```html
    <section class="bg-base-100 rounded-2xl border border-base-300 shadow-xs flex flex-col h-full overflow-hidden">
    ```

#### 2. Standar Sidebar Navigasi (`AppSidebar.vue`)
- **Latar & Batas**: `bg-base-100 border-r border-base-300`.
- **Tinggi Header**: `h-16` (64px), sejajar presisi dengan tinggi Topbar.
- **Brand Title**: Teks tebal murni `text-[15px] font-black text-base-content tracking-tight` (tanpa ikon).
- **Header Kategori**: Menggunakan judul kategori huruf kapital tebal: `text-[10px] font-extrabold text-base-content/40 uppercase tracking-wider px-2 pt-2 pb-1.5` (contoh: `MAIN MENU`).
- **Menu Aktif**: Berbentuk **kapsul pekat solid** PLN Primary:
  ```html
  class="bg-primary text-primary-content font-semibold rounded-xl px-3.5 py-2.5 shadow-xs flex items-center gap-3"
  ```
- **Menu Tidak Aktif**: Ikon garis dan teks netral:
  ```html
  class="text-base-content/60 hover:bg-base-200 hover:text-base-content font-medium rounded-xl px-3.5 py-2.5 flex items-center gap-3 transition-colors"
  ```
- **Kartu Status Gradien di Bawah**: Kartu lengkung modern `rounded-2xl bg-gradient-to-br from-primary to-secondary text-primary-content p-4 shadow-sm` berisi status online gateway dengan lampu hijau berkedip.
- **Perilaku Hover-to-Expand**:
  - Saat mode ikon aktif (`isCollapsed = true`), sidebar menyusut ke `w-18` (72px) dan diberi *placeholder* agar layout konten tidak bergeser.
  - Saat di-hover (`@mouseenter`), sidebar melebar secara dinamis ke `w-60` (240px) sebagai floating overlay (`shadow-2xl z-50`).
  - Saat kursor lepas (`@mouseleave`), kembali ke `w-18`.

#### 3. Standar Topbar / Navbar (`AppNavbar.vue`)
- **Tinggi & Batas**: `h-16` (64px) dengan `bg-base-100 border-b border-base-300 px-6`.
- **Bilah Pencarian Global**: Kapsul lonjong (*rounded-full*) di sisi kiri:
  ```html
  <input type="text" placeholder="Cari..." class="input input-sm w-full bg-base-200 border-0 rounded-full pl-10 pr-4 text-xs" />
  ```
- **Kontrol Kanan**: Tombol theme switcher, notifikasi (dengan dot kuning `bg-accent`), pemisah garis tipis vertikal `h-6 w-px bg-base-300`, dan pil profil pengguna (avatar gradasi bulat + nama + jabatan).

#### 4. Aturan Pembuatan Halaman Baru
Setiap kali menambahkan halaman baru (misal: `/app/customers`, `/app/tickets`, `/app/dashboard`):
- Jangan buat halaman satu lembar putih polos menempel ke pinggir.
- Bungkus selalu dengan `div class="h-full p-4 flex flex-col gap-4 bg-base-200 overflow-y-auto"`.
- Tempatkan tabel atau ringkasan data di dalam kartu `BaseCard` atau container `bg-base-100 rounded-2xl border border-base-300 shadow-xs p-5`.

---

## 8. Komponen dasar

Seluruh komponen dasar terdaftar secara global via `plugins/global.js`: `BaseButton`, `BaseInput`, `BaseCard`, `BaseTable`, `BaseDialog`, `BaseLoading`, `BaseEmpty`, `BaseBadge`, `BasePagination`, `BaseStat`.

### Aturan Visual & Konsistensi Komponen (Seragam dengan Desain Sidebar/Navbar):
1. **Hierarki Sudut (Border Radius)**:
   - Kartu kontainer & dialog (`BaseCard`, `BaseTable`, `BaseDialog`, `BaseStat`): Wajib **`rounded-2xl`** dengan batas solid `border border-base-300 shadow-xs`.
   - Elemen interaktif (`BaseButton`, `BaseInput`, pagination pill): Menggunakan **`rounded-xl`** (kecuali tombol `xs` memakai `rounded-lg`).
   - Status & pill (`BaseBadge`): Menggunakan **`rounded-lg`** atau `rounded-full` (*pill*).
   - Indikator skeleton (`BaseLoading`): Wajib **`rounded-xl`** atau `rounded-lg` (dilarang memakai sudut tajam `rounded-sm`).
2. **Karakteristik Tombol (`BaseButton`)**:
   - `primary`: Solid `btn-primary text-primary-content font-semibold shadow-xs` (kapsul PLN).
   - `accent`: Solid `btn-accent text-accent-content font-bold shadow-xs` (kuning aksi interaktif).
   - `outline`: `bg-transparent border border-base-300 text-base-content hover:bg-base-200`.
   - `ghost`: `bg-transparent border-0 text-base-content/70 hover:bg-base-200 hover:text-base-content`.
3. **Standar Field Input (`BaseInput`)**:
   - Input menggunakan `bg-base-100 border border-base-300 rounded-xl shadow-xs text-sm focus:border-primary focus:ring-1 focus:ring-primary`.
   - Label menggunakan huruf kapital tebal: `text-[11px] font-bold text-base-content/70 uppercase tracking-wider`.
4. **Tabel & Paginasi (`BaseTable`, `BasePagination`)**:
   - `BaseTable` menerima `columns`, `rows`, `loading`, dan slot per sel (`#cell-[key]`). Terintegrasi langsung dengan `BaseLoading` dan `BaseEmpty`.
   - `BasePagination` menerima `meta` (`current_page`, `last_page`, `total`, `from`, `to`), dengan tombol nomor halaman kapsul aktif `bg-primary text-primary-content`.
5. **Modal & Konfirmasi (`BaseDialog`)**:
   - Menampilkan dialog konfirmasi dengan backdrop blur halus, ikon squircle `rounded-2xl`, judul tebal, dan tombol aksi `BaseButton`.
6. **Kartu Ringkasan Metrik (`BaseStat`)**:
   - Kontainer `rounded-2xl border border-base-300 p-5 shadow-xs`, label kapital `text-[11px] font-bold text-base-content/60 uppercase`, nilai besar `text-2xl font-black`, dan wadah ikon squircle `rounded-2xl bg-base-200`.

---

## 9. Aturan keamanan frontend

1. **Tidak ada rahasia di frontend.** Hanya `VITE_BASEURL` yang boleh ada di `.env` frontend. Kunci LLM, kredensial WhatsApp, dan secret webhook tidak pernah masuk ke bundle.
2. **Render teks apa adanya.** Pesan customer, jawaban AI, nama, dan catatan ditampilkan dengan `{{ }}`. Dilarang memakai `v-html` untuk data dari pengguna. Jika HTML memang dibutuhkan (isi artikel FAQ), sanitasi dengan DOMPurify sebelum ditampilkan.
3. **Token.** Disimpan di IndexedDB untuk prototipe (bisa dibaca JavaScript, jadi pencegahan XSS wajib). Untuk produksi, pertimbangkan cookie HttpOnly lewat mode SPA Sanctum.
4. **Hanya 401 yang me-logout.** 403 cukup menampilkan pesan tidak berhak.
5. **Pengecekan role di frontend hanya tampilan.** Setiap izin diputuskan backend.
6. **Redirect hanya ke path internal** (lihat `safeRedirect`).
7. **Tidak ada `console.log` data pelanggan** (nomor telepon, isi chat) di build produksi.
8. **Dependensi:** jalankan `npm audit` secara berkala dan kunci versi lewat lockfile.
9. **Simulator** hanya terdaftar saat mode development dan tidak ikut ke build produksi.

---

## 10. Checklist per halaman baru

- [ ] Service dan store mengikuti konvensi (`s$`, `g$`, `a$`).
- [ ] Ada tampilan loading, kosong, dan error.
- [ ] Error backend tampil lewat toast dari `errors[0].details`.
- [ ] Tidak ada `v-html` untuk data pengguna.
- [ ] Route berada di bawah induk `/app` sehingga mewarisi `meta.auth` dan `meta.roles`.
- [ ] Responsif minimal sampai lebar ponsel.
- [ ] Hanya memakai token warna DaisyUI.