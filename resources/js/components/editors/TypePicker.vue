<template>
  <div class="shrink-0">
    <label :for="id" class="sr-only">{{ __('Value type') }}</label>
    <select
      :id="id"
      class="form-control form-select form-select-bordered h-7 py-0 font-mono text-xxs"
      :value="modelValue"
      :disabled="disabled"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-for="type in allowed" :key="type" :value="type">{{ type }}</option>
    </select>
  </div>
</template>

<script>
import { TYPES } from '../../support/json'
import { uid } from '../../support/uid'

export default {
  name: 'TypePicker',

  // A real <select> rather than a custom listbox: it is keyboard- and
  // screen-reader-correct for free, and a bespoke one buys nothing here.
  props: {
    modelValue: { type: String, required: true },
    allow: { type: Array, default: () => TYPES },
    disabled: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  data: () => ({ id: uid('njf-type') }),

  computed: {
    allowed() {
      return this.allow.filter(type => TYPES.includes(type))
    },
  },
}
</script>
