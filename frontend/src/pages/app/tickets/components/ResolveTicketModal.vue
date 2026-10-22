<script setup>
import { ref, watch } from 'vue'

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

const resolutionNotesInput = ref('')

watch(
  () => props.open,
  (val) => {
    if (val) {
      resolutionNotesInput.value = ''
    }
  }
)

const handleConfirm = () => {
  emit('confirm', resolutionNotesInput.value.trim() || null)
}

const handleCancel = () => {
  emit('update:open', false)
  emit('cancel')
}
</script>

<template>
  <BaseDialog
    :open="open"
    title="Selesaikan Penanganan Tiket"
    confirm-text="Selesaikan & Pulihkan AI"
    cancel-text="Batal"
    variant="accent"
    :loading="loading"
    @confirm="handleConfirm"
    @cancel="handleCancel"
  >
    <div v-if="ticket" class="space-y-3 pt-2">
      <p class="text-xs text-base-content/80">
        Tiket <strong class="text-base-content">{{ ticket.ticket_code }}</strong> milik
        <strong class="text-base-content">{{ ticket.customer_name }}</strong> akan ditandai
        <strong class="text-emerald-500">Selesai (Resolved)</strong>.
      </p>

      <div class="space-y-1.5">
        <label class="text-xs font-bold text-base-content">
          Catatan Solusi / Penyelesaian (Opsional):
        </label>
        <textarea
          v-model="resolutionNotesInput"
          rows="3"
          placeholder="Contoh: Saldo selisih 50 pts berhasil disinkronkan manual ke akun driver."
          class="textarea textarea-sm w-full bg-base-200 border border-base-300 rounded-xl text-xs focus:outline-none leading-relaxed"
        ></textarea>
        <p class="text-[11px] text-base-content/50">
          Catatan ini akan tersimpan di riwayat tiket dan disampaikan dalam pesan penutup ke WhatsApp pelanggan. Setelah ini, bot AI otomatis aktif kembali untuk melayani pelanggan.
        </p>
      </div>
    </div>
  </BaseDialog>
</template>
