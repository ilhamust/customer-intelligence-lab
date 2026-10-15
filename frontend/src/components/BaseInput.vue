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
  <div :class="['form-control space-y-1', { 'w-full': block }]">
    <label v-if="label" class="label p-0">
      <span class="label-text text-xs font-semibold text-base-content/80">{{ label }}</span>
    </label>
    
    <div class="relative flex items-center">
      <div v-if="$slots.prefix" class="absolute left-3 flex items-center pointer-events-none text-base-content/50">
        <slot name="prefix" />
      </div>

      <input
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="input input-bordered focus:border-primary focus:outline-none transition-colors"
        :class="[
          `input-${size}`,
          { 'w-full': block },
          { 'pl-10': $slots.prefix },
          { 'pr-10': $slots.suffix },
          { 'input-error': error }
        ]"
        @input="$emit('update:modelValue', $event.target.value)"
      />

      <div v-if="$slots.suffix" class="absolute right-3 flex items-center text-base-content/50">
        <slot name="suffix" />
      </div>
    </div>

    <p v-if="error" class="text-xs text-error mt-1 font-medium">{{ error }}</p>
  </div>
</template>
