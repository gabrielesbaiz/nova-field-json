<template>
  <input
    v-if="type === 'boolean'"
    type="checkbox"
    class="form-checkbox"
    :checked="modelValue === true"
    :disabled="readonly"
    :aria-invalid="invalid || null"
    :aria-label="__('Value')"
    @change="$emit('update:modelValue', $event.target.checked)"
  />

  <span
    v-else-if="type === 'null'"
    class="font-mono text-xs italic text-gray-400 dark:text-gray-500"
  >
    null
  </span>

  <input
    v-else
    ref="input"
    :type="type === 'number' ? 'number' : 'text'"
    class="form-control form-input form-input-bordered h-7 w-full min-w-0 py-0 font-mono text-xs"
    :class="invalid ? 'form-control-bordered-error' : ''"
    :value="draft"
    :readonly="readonly"
    :aria-invalid="invalid || null"
    :aria-label="__('Value')"
    @focus="begin"
    @input="draft = $event.target.value"
    @keydown.enter.prevent="commit"
    @keydown.esc.prevent="cancel"
    @blur="commit"
  />
</template>

<script>
import { coerce } from '../../support/json'

export default {
  name: 'ValueInput',

  props: {
    modelValue: { default: null },
    type: { type: String, required: true },
    readonly: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
  },

  emits: ['update:modelValue'],

  data() {
    return { draft: this.asText(this.modelValue), before: null }
  },

  watch: {
    modelValue(next) {
      this.draft = this.asText(next)
    },
  },

  methods: {
    asText(value) {
      return value === null || value === undefined ? '' : String(value)
    },

    begin() {
      // Snapshot locally rather than reading the model on Escape: by then the
      // model may already have moved on via another edit.
      this.before = this.draft
    },

    commit() {
      const next = coerce(this.draft, this.type)

      if (next !== this.modelValue) {
        this.$emit('update:modelValue', next)
      }
    },

    cancel() {
      this.draft = this.before ?? this.asText(this.modelValue)

      this.$refs.input?.blur()
    },
  },
}
</script>
