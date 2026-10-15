<script setup>
defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Konfirmasi Tindakan',
  },
  message: {
    type: String,
    default: '',
  },
  confirmText: {
    type: String,
    default: 'Ya, Lanjutkan',
  },
  cancelText: {
    type: String,
    default: 'Batal',
  },
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'error', 'warning', 'accent'].includes(v),
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['confirm', 'cancel', 'update:open'])
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs select-none"
      @click.self="$emit('cancel')"
    >
      <div class="bg-base-100 rounded-2xl border border-base-300 shadow-2xl max-w-md w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
        <!-- Dialog Header & Icon -->
        <div class="flex items-start gap-4">
          <div
            class="w-11 h-11 rounded-2xl border flex items-center justify-center shrink-0 shadow-xs"
            :class="[
              variant === 'error'
                ? 'bg-error/10 border-error/30 text-error'
                : variant === 'warning'
                  ? 'bg-warning/10 border-warning/30 text-warning'
                  : 'bg-base-200 border-base-300 text-primary'
            ]"
          >
            <slot name="icon">
              <!-- Warning / Alert Triangle Icon -->
              <svg v-if="variant === 'error' || variant === 'warning'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                <line x1="12" y1="9" x2="12" y2="13" />
                <line x1="12" y1="17" x2="12.01" y2="17" />
              </svg>
              <!-- Info / Question Icon -->
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="16" x2="12" y2="12" />
                <line x1="12" y1="8" x2="12.01" y2="8" />
              </svg>
            </slot>
          </div>

          <div class="space-y-1">
            <h3 class="font-bold text-base text-base-content tracking-tight">{{ title }}</h3>
            <p v-if="message" class="text-xs text-base-content/70 leading-relaxed">{{ message }}</p>
            <slot />
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2.5 pt-2">
          <BaseButton
            variant="outline"
            size="sm"
            :disabled="loading"
            @click="$emit('cancel')"
          >
            {{ cancelText }}
          </BaseButton>

          <BaseButton
            :variant="variant"
            size="sm"
            :loading="loading"
            @click="$emit('confirm')"
          >
            {{ confirmText }}
          </BaseButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
