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
const qrImage = ref('')
const countdown = ref(30)
let timer = null
let pollTimer = null

const cleanupTimers = () => {
  if (timer) clearInterval(timer)
  if (pollTimer) clearInterval(pollTimer)
  timer = null
  pollTimer = null
}

const checkConnectionStatus = async () => {
  if (!props.open || !props.device?.device_id) return

  try {
    const res = await p$device.a$getStatus(props.device.device_id)
    const isConnected = res?.status === 'connected' || res?.is_logged_in === true

    if (isConnected) {
      cleanupTimers()
      notify.success(`Device ${props.device.name || props.device.device_id} successfully connected!`)
      await p$device.a$fetchList()
      p$device.a$select(props.device.device_id)
      emit('close')
    }
  } catch (_) {
    // Ignore transient errors during polling
  }
}

const fetchQr = async () => {
  if (!props.device?.device_id) return
  loading.value = true
  qrImage.value = ''
  cleanupTimers()

  try {
    const res = await p$device.a$getLoginQr(props.device.device_id)
    qrImage.value = res?.qr_image || ''
    countdown.value = Number(res?.duration || 30)

    // Countdown timer
    timer = setInterval(() => {
      if (countdown.value > 0) {
        countdown.value--
      } else {
        cleanupTimers()
      }
    }, 1000)

    // Poll status every 2 seconds
    pollTimer = setInterval(checkConnectionStatus, 2000)
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to retrieve QR Code')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.open,
  (val) => {
    if (val && props.device) {
      fetchQr()
    } else {
      cleanupTimers()
      qrImage.value = ''
    }
  }
)

onBeforeUnmount(() => {
  cleanupTimers()
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-sm w-full p-6 space-y-5 text-center animate-in fade-in zoom-in-95 duration-150">
        <!-- Header -->
        <div class="flex items-start justify-between gap-2">
          <div class="text-left space-y-1">
            <h3 class="font-bold text-base text-base-content tracking-tight">Login with QR Code</h3>
            <p class="text-xs text-base-content/60">
              Scan this QR code from the <strong>Linked Devices</strong> menu on your WhatsApp phone.
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

        <!-- QR Display Container -->
        <div class="py-2">
          <div v-if="loading" class="h-64 flex items-center justify-center">
            <BaseLoading text="Preparing QR Code from gateway..." />
          </div>

          <div v-else-if="qrImage" class="space-y-3">
            <div class="inline-block p-4 bg-white rounded-2xl shadow-xs border border-base-300">
              <img :src="qrImage" alt="WhatsApp Login QR" class="w-52 h-52 mx-auto object-contain" />
            </div>

            <div class="flex items-center justify-center gap-2">
              <BaseBadge
                :variant="countdown > 5 ? 'neutral' : 'warning'"
                pill
                size="sm"
              >
                Expires in {{ countdown }}s
              </BaseBadge>

              <button
                type="button"
                @click="fetchQr"
                class="btn btn-ghost btn-xs rounded-lg text-primary hover:bg-base-200"
                title="Reload QR Code"
              >
                Refresh
              </button>
            </div>
          </div>

          <div v-else class="h-64 flex flex-col items-center justify-center space-y-3">
            <p class="text-xs text-base-content/60">Unable to load QR Code.</p>
            <BaseButton variant="outline" size="sm" @click="fetchQr">Try Again</BaseButton>
          </div>
        </div>

        <!-- Footer -->
        <div class="pt-2 border-t border-base-300">
          <BaseButton variant="outline" size="sm" block @click="$emit('close')">
            Close
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
