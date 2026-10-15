<script setup>
import { ref, watch } from 'vue'
import { useDeviceStore } from '@/stores/app/device.store'
import { notify } from '@/plugins'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'created'])

const p$device = useDeviceStore()
const loading = ref(false)

const form = ref({
  deviceId: '',
  name: '',
  webhookUrl: '',
  webhookSecret: '',
})

watch(
  () => props.open,
  (val) => {
    if (val) {
      form.value = {
        deviceId: '',
        name: '',
        webhookUrl: '',
        webhookSecret: '',
      }
    }
  }
)

const handleSubmit = async () => {
  if (!form.value.name.trim()) {
    notify.error('Device name is required')
    return
  }

  loading.value = true
  try {
    const payload = {
      device_id: form.value.deviceId.trim() || `device_${Date.now()}`,
      name: form.value.name.trim(),
    }
    await p$device.a$register(payload)
    notify.success(`Device '${payload.name}' successfully added!`)
    emit('created')
    emit('close')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Failed to add device')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('close')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-3">
          <div class="space-y-1">
            <h3 class="font-bold text-base text-base-content tracking-tight">Add device</h3>
            <p class="text-xs text-base-content/60 leading-relaxed">
              Registers a device slot. Pair it with a phone afterwards via QR or pairing code.
            </p>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content shrink-0"
            title="Close dialog"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 6 6 18" />
              <path d="m6 6 12 12" />
            </svg>
          </button>
        </div>

        <!-- Form Body -->
        <form @submit.prevent="handleSubmit" class="space-y-4">
          <BaseInput
            v-model="form.name"
            label="Device Name"
            placeholder="e.g. Gateway CS 1"
            :disabled="loading"
          />

          <BaseInput
            v-model="form.deviceId"
            label="Device ID (optional)"
            placeholder="auto-generated when empty"
            :disabled="loading"
          />

          <BaseInput
            v-model="form.webhookUrl"
            label="Webhook URL (optional)"
            placeholder="https://example.com/webhook"
            :disabled="loading"
          />

          <BaseInput
            v-model="form.webhookSecret"
            type="password"
            label="Webhook secret (optional)"
            placeholder="Secret key for verification"
            :disabled="loading"
          />

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-base-300">
            <BaseButton
              type="button"
              variant="outline"
              size="sm"
              :disabled="loading"
              @click="$emit('close')"
            >
              Cancel
            </BaseButton>

            <BaseButton
              type="submit"
              variant="primary"
              size="sm"
              :loading="loading"
            >
              Add device
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>
