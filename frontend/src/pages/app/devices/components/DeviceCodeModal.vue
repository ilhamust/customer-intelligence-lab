<script setup>
import { ref, watch } from 'vue'
import { useDeviceStore } from '@/stores/app/device.store'
import { notify } from '@/plugins'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  device: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])

const p$device = useDeviceStore()
const loading = ref(false)
const phone = ref('')
const pairCode = ref('')

watch(
  () => props.open,
  (val) => {
    if (val) {
      phone.value = props.device?.phone_number || ''
      pairCode.value = ''
    }
  }
)

const handleGetCode = async () => {
  const cleanPhone = phone.value.replace(/\D/g, '')
  if (!cleanPhone || cleanPhone.length < 8) {
    notify.error('Nomor telepon WhatsApp tidak valid')
    return
  }

  loading.value = true
  try {
    const res = await p$device.a$getLoginCode(props.device.device_id, cleanPhone)
    pairCode.value = res?.pair_code || ''
    notify.success('Kode pairing berhasil dibuat!')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal mendapatkan kode pairing')
  } finally {
    loading.value = false
  }
}

const formatPairCode = (code) => {
  if (!code) return ''
  const clean = code.replace(/-/g, '').toUpperCase()
  if (clean.length === 8) {
    return `${clean.slice(0, 4)} - ${clean.slice(4)}`
  }
  return code
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-sm w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <!-- Header -->
        <div class="flex items-start justify-between gap-2">
          <div class="space-y-1">
            <h3 class="font-bold text-base text-base-content tracking-tight">Login with Code</h3>
            <p class="text-xs text-base-content/60">
              Tautkan WhatsApp menggunakan 8-digit kode pairing tanpa memindai QR code.
            </p>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content shrink-0"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </div>

        <!-- Step 1: Input Phone Number -->
        <div v-if="!pairCode" class="space-y-4">
          <BaseInput
            v-model="phone"
            label="Nomor WhatsApp Ponsel"
            placeholder="Contoh: 6281234567890"
            :disabled="loading"
          />

          <BaseButton
            variant="primary"
            size="sm"
            block
            :loading="loading"
            @click="handleGetCode"
          >
            Dapatkan Kode Pairing
          </BaseButton>
        </div>

        <!-- Step 2: Show 8-Digit Pairing Code -->
        <div v-else class="space-y-4 text-center">
          <div class="p-5 bg-base-200 rounded-2xl border border-base-300 shadow-inner">
            <p class="text-[11px] font-bold text-base-content/60 uppercase tracking-wider mb-2">Kode Pairing Anda</p>
            <div class="font-mono font-black text-2xl tracking-widest text-primary select-all">
              {{ formatPairCode(pairCode) }}
            </div>
          </div>

          <p class="text-xs text-base-content/70 leading-relaxed">
            Buka notifikasi WhatsApp di ponsel Anda, lalu masukkan 8 karakter kode di atas untuk menyelesaikan penautan.
          </p>

          <BaseButton
            variant="outline"
            size="sm"
            block
            @click="$emit('close')"
          >
            Selesai
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
