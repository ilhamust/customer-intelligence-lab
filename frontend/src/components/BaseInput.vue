<script setup>
defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  label: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  error: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
  },
  block: {
    type: Boolean,
    default: true,
  },
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div :class="['form-control space-y-1.5', { 'w-full': block }]">
    <label v-if="label" class="label p-0">
      <span class="text-[11px] font-bold text-base-content/70 uppercase tracking-wider">{{ label }}</span>
    </label>
    
    <div class="relative flex items-center">
      <div v-if="$slots.prefix" class="absolute left-3.5 flex items-center pointer-events-none text-base-content/40">
        <slot name="prefix" />
      </div>

      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="input w-full bg-base-100 border border-base-300 text-base-content placeholder:text-base-content/40 focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none shadow-xs transition-all disabled:bg-base-200 disabled:opacity-70 disabled:cursor-not-allowed"
        :class="[
          size === 'xs' ? 'h-7 text-xs rounded-lg px-2.5' : '',
          size === 'sm' ? 'h-9 text-xs rounded-xl px-3.5' : '',
          size === 'md' ? 'h-10 text-sm rounded-xl px-4' : '',
          size === 'lg' ? 'h-12 text-base rounded-xl px-5' : '',
          { 'pl-10': $slots.prefix },
          { 'pr-10': $slots.suffix },
          { '!border-error focus:!ring-error': error }
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
      />

      <div v-if="$slots.suffix" class="absolute right-3.5 flex items-center text-base-content/40">
        <slot name="suffix" />
      </div>
    </div>

    <p v-if="error" class="text-xs text-error font-medium mt-1">{{ error }}</p>
  </div>
</template>
