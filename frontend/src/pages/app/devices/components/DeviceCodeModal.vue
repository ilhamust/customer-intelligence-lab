<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
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
let pollTimer = null

const cleanupPoll = () => {
  if (pollTimer) clearInterval(pollTimer)
  pollTimer = null
}

const checkConnectionStatus = async () => {
  if (!props.open || !props.device?.device_id) return

  try {
    const res = await p$device.a$getStatus(props.device.device_id)
    const isConnected = res?.status === 'connected' || res?.is_logged_in === true

    if (isConnected) {
      cleanupPoll()
      notify.success(`Device ${props.device.name || props.device.device_id} successfully connected!`)
      await p$device.a$fetchList()
      p$device.a$select(props.device.device_id)
      emit('close')
    }
  } catch (_) {
    // Ignore transient polling errors
  }
}

watch(
  () => props.open,
  (val) => {
    cleanupPoll()
    if (val) {
      phone.value = props.device?.phone_number || ''
      pairCode.value = ''
    }
  }
)

const handleGetCode = async () => {
  const cleanPhone = phone.value.replace(/\D/g, '')
  if (!cleanPhone || cleanPhone.length < 8) {
    notify.error('Invalid WhatsApp phone number')
    return
  }

  loading.value = true
  cleanupPoll()

  try {
    const res = await p$device.a$getLoginCode(props.device.device_id, cleanPhone)
    pairCode.value = res?.pair_code || ''
    notify.success('Pairing code generated successfully!')

    // Poll status every 2.5 seconds
    pollTimer = setInterval(checkConnectionStatus, 2500)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to generate pairing code')
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

onBeforeUnmount(() => {
  cleanupPoll()
})
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
              Link WhatsApp using an 8-digit pairing code without scanning a QR code.
            </p>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content shrink-0"
            title="Close"
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
            label="WhatsApp Phone Number"
            placeholder="e.g. 6281234567890"
            :disabled="loading"
          />

          <BaseButton
            variant="primary"
            size="sm"
            block
            :loading="loading"
            @click="handleGetCode"
          >
            Get Pairing Code
          </BaseButton>
        </div>

        <!-- Step 2: Show 8-Digit Pairing Code -->
        <div v-else class="space-y-4 text-center">
          <div class="p-5 bg-base-200 rounded-2xl border border-base-300 shadow-inner">
            <p class="text-[11px] font-bold text-base-content/60 uppercase tracking-wider mb-2">Your Pairing Code</p>
            <div class="font-black text-3xl tracking-widest text-primary select-all tabular-nums">
              {{ formatPairCode(pairCode) }}
            </div>
          </div>

          <p class="text-xs text-base-content/70 leading-relaxed">
            Open the WhatsApp notification on your phone, then enter the 8 characters above to complete pairing.
          </p>

          <BaseButton
            variant="outline"
            size="sm"
            block
            @click="$emit('close')"
          >
            Done
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
