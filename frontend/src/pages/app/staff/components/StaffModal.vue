<script setup>
import { ref, watch } from 'vue'
import { useStaffStore } from '@/stores/app/staff.store'
import { notify } from '@/plugins'
import BaseInput from '@/components/BaseInput.vue'
import BaseButton from '@/components/BaseButton.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  staffData: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close', 'saved'])

const p$staff = useStaffStore()
const loading = ref(false)

const form = ref({
  name: '',
  phone: '',
  role: 'field_technician',
  station_name: 'all',
  is_on_duty: true,
  is_active: true,
  notes: '',
})

const roleOptions = [
  { value: 'field_technician', label: 'Teknisi Lapangan (Field Technician)' },
  { value: 'site_operator', label: 'Operator Lokasi (Site Operator)' },
  { value: 'station_manager', label: 'Supervisor / Manajer Stasiun' },
]

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      if (props.staffData) {
        form.value = {
          name: props.staffData.name || '',
          phone: props.staffData.phone || '',
          role: props.staffData.role || 'field_technician',
          station_name: props.staffData.station_name || 'all',
          is_on_duty: props.staffData.is_on_duty ?? true,
          is_active: props.staffData.is_active ?? true,
          notes: props.staffData.notes || '',
        }
      } else {
        form.value = {
          name: '',
          phone: '',
          role: 'field_technician',
          station_name: 'all',
          is_on_duty: true,
          is_active: true,
          notes: '',
        }
      }
    }
  }
)

const handleSubmit = async () => {
  if (!form.value.name.trim()) {
    notify.error('Nama staf wajib diisi')
    return
  }

  if (!form.value.phone.trim()) {
    notify.error('Nomor WhatsApp wajib diisi')
    return
  }

  loading.value = true
  try {
    const payload = {
      name: form.value.name.trim(),
      phone: form.value.phone.trim(),
      role: form.value.role,
      station_name: form.value.station_name.trim() || 'all',
      is_on_duty: form.value.is_on_duty,
      is_active: form.value.is_active,
      notes: form.value.notes?.trim() || null,
    }

    if (props.staffData?.id) {
      await p$staff.a$updateStaff(props.staffData.id, payload)
      notify.success(`Data staf ${payload.name} berhasil diperbarui!`)
    } else {
      await p$staff.a$createStaff(payload)
      notify.success(`Kontak staf ${payload.name} berhasil ditambahkan!`)
    }

    emit('saved')
    emit('close')
  } catch (e) {
    notify.error(e?.message ?? e ?? 'Gagal menyimpan data staf')
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
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-3 border-b border-base-300 pb-3">
          <div class="space-y-1">
            <h3 class="font-bold text-base text-base-content tracking-tight">
              {{ staffData?.id ? 'Edit Kontak Staf Lapangan' : 'Tambah Petugas Lapangan Baru' }}
            </h3>
            <p class="text-xs text-base-content/60 leading-relaxed">
              Nomor WhatsApp yang terdaftar dan berstatus <strong class="text-success font-semibold">On-Duty</strong> akan otomatis menerima notifikasi insiden darurat 24/7.
            </p>
          </div>

          <button
            type="button"
            @click="$emit('close')"
            class="btn btn-ghost btn-sm btn-circle text-base-content/50 hover:text-base-content shrink-0"
            title="Tutup dialog"
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
            label="Nama Lengkap Petugas"
            placeholder="cth: Budi Santoso"
            :disabled="loading"
            required
          />

          <BaseInput
            v-model="form.phone"
            label="Nomor WhatsApp (Otomatis Dinormalisasi)"
            placeholder="cth: 0812-3456-7890 atau 628123456789"
            :disabled="loading"
            required
          />

          <!-- Role Select -->
          <div class="space-y-1.5 text-left">
            <label class="block text-xs font-bold text-base-content/80">
              Peran / Jabatan
            </label>
            <select
              v-model="form.role"
              :disabled="loading"
              class="select select-bordered select-sm w-full rounded-xl text-xs bg-base-100 focus:outline-primary"
            >
              <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Station Name -->
          <BaseInput
            v-model="form.station_name"
            label="Penempatan Stasiun SPKLU"
            placeholder="Ketik 'all' untuk seluruh stasiun atau nama stasiun spesifik"
            :disabled="loading"
          />

          <!-- Duty & Active Status Toggles -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <!-- On-Duty Toggle -->
            <div class="flex items-center justify-between p-3 rounded-xl border border-base-300 bg-base-200/50">
              <div class="space-y-0.5">
                <span class="text-xs font-bold text-base-content flex items-center gap-1.5">
                  <span
                    class="w-2 h-2 rounded-full"
                    :class="form.is_on_duty ? 'bg-success animate-pulse' : 'bg-base-content/30'"
                  ></span>
                  Status Siaga (On-Duty)
                </span>
                <p class="text-[10px] text-base-content/60">
                  {{ form.is_on_duty ? 'Menerima alert darurat WhatsApp' : 'Sedang libur / off-duty' }}
                </p>
              </div>
              <input
                type="checkbox"
                v-model="form.is_on_duty"
                :disabled="loading"
                class="toggle toggle-success toggle-sm"
              />
            </div>

            <!-- Active Status Toggle -->
            <div class="flex items-center justify-between p-3 rounded-xl border border-base-300 bg-base-200/50">
              <div class="space-y-0.5">
                <span class="text-xs font-bold text-base-content">
                  Status Staf Aktif
                </span>
                <p class="text-[10px] text-base-content/60">
                  {{ form.is_active ? 'Petugas aktif di sistem' : 'Non-aktif / keluar' }}
                </p>
              </div>
              <input
                type="checkbox"
                v-model="form.is_active"
                :disabled="loading"
                class="toggle toggle-primary toggle-sm"
              />
            </div>
          </div>

          <!-- Notes -->
          <div class="space-y-1.5 text-left">
            <label class="block text-xs font-bold text-base-content/80">
              Catatan Khusus / Shift / Spesialisasi
            </label>
            <textarea
              v-model="form.notes"
              rows="2"
              :disabled="loading"
              placeholder="cth: Shift malam (20:00 - 08:00 WIB), Ahli kelistrikan fast-charging DC"
              class="textarea textarea-bordered textarea-sm w-full rounded-xl text-xs bg-base-100 focus:outline-primary"
            ></textarea>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-base-300">
            <BaseButton
              type="button"
              variant="outline"
              size="sm"
              :disabled="loading"
              @click="$emit('close')"
            >
              Batal
            </BaseButton>

            <BaseButton
              type="submit"
              variant="primary"
              size="sm"
              :loading="loading"
            >
              {{ staffData?.id ? 'Simpan Perubahan' : 'Tambah Petugas' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

