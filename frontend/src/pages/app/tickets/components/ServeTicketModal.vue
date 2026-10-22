<script setup>
import { ref, watch } from 'vue'
import { formatPhone } from '@/utils/helper'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  ticket: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:open', 'confirm', 'cancel'])

const adminNameInput = ref('Admin Customer Care')

watch(
  () => props.open,
  (val) => {
    if (val) {
      adminNameInput.value = 'Admin Customer Care'
    }
  }
)

const handleConfirm = () => {
  emit('confirm', adminNameInput.value.trim() || 'Admin Customer Care')
}

const handleCancel = () => {
  emit('update:open', false)
  emit('cancel')
}
</script>

<template>
  <BaseDialog
    :open="open"
    title="Layani Tiket Pelanggan"
    confirm-text="Mulai Layani & Sapa"
    cancel-text="Batal"
    variant="primary"
    :loading="loading"
    @confirm="handleConfirm"
    @cancel="handleCancel"
  >
    <div v-if="ticket" class="space-y-3 pt-2">
      <p class="text-xs text-base-content/80">
        Anda akan mengambil alih penanganan tiket
        <strong class="text-base-content">{{ ticket.ticket_code }}</strong> untuk pelanggan
        <strong class="text-base-content">{{ ticket.customer_name }}</strong>.
      </p>

      <div class="p-3 bg-base-200 rounded-xl space-y-1.5 text-xs">
        <div class="flex justify-between">
          <span class="text-base-content/60">Kategori:</span>
          <span class="font-bold">{{ ticket.category_label }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-base-content/60">Nomor WhatsApp:</span>
          <span class="font-mono">{{ formatPhone(ticket.customer_phone) }}</span>
        </div>
      </div>

      <div class="space-y-1">
        <label class="text-xs font-bold text-base-content">Nama CS untuk Sapaan WhatsApp:</label>
        <input
          v-model="adminNameInput"
          type="text"
          placeholder="Contoh: Admin Budi / Customer Care"
          class="input input-sm w-full bg-base-200 border border-base-300 rounded-xl text-xs focus:outline-none"
        />
        <p class="text-[11px] text-base-content/50">
          Sistem otomatis mengirim sapaan pembuka dari nama ini ke WhatsApp pelanggan.
        </p>
      </div>
    </div>
  </BaseDialog>
</template>
