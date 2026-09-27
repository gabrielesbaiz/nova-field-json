<template>
  <div
    role="radiogroup"
    :aria-label="__('Editor mode')"
    class="inline-flex overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
  >
    <button
      v-for="(mode, index) in modes"
      :key="mode"
      type="button"
      role="radio"
      :aria-checked="mode === modelValue"
      :tabindex="mode === modelValue ? 0 : -1"
      :disabled="disabled"
      class="px-3 py-1 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 disabled:opacity-50"
      :class="mode === modelValue
        ? 'bg-primary-500 text-white'
        : 'bg-white text-gray-500 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800'"
      @click="$emit('update:modelValue', mode)"
      @keydown.left.prevent="step(index, -1)"
      @keydown.right.prevent="step(index, 1)"
    >
      {{ label(mode) }}
    </button>
  </div>
</template>

<script>
export default {
  name: 'ModeSwitcher',

  props: {
    modelValue: { type: String, required: true },
    modes: { type: Array, default: () => ['tree', 'keyvalue', 'raw'] },
    disabled: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  methods: {
    label(mode) {
      switch (mode) {
        case 'raw':
          return this.__('Raw')
        case 'keyvalue':
          return this.__('Key / Value')
        default:
          return this.__('Tree')
      }
    },

    step(index, delta) {
      const next = this.modes[(index + delta + this.modes.length) % this.modes.length]

      this.$emit('update:modelValue', next)
    },
  },
}
</script>
