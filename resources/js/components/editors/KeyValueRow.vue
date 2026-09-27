<template>
  <div
    class="group flex items-center gap-2 border-b border-gray-100 px-2 py-1.5 last:border-b-0 dark:border-gray-700"
    @dragover="onDragOver"
    @drop.prevent="onDrop"
  >
    <DragHandle v-if="!readonly" @dragstart="onDragStart" @dragend="onDragEnd" />

    <div class="w-40 shrink-0">
      <label :for="`${id}-key`" class="sr-only">{{ __('Key') }}</label>
      <input
        :id="`${id}-key`"
        class="form-control form-input form-input-bordered h-7 w-full py-0 font-mono text-xs"
        :class="keyProblem ? 'form-control-bordered-error' : ''"
        :value="row.key"
        :readonly="readonly"
        :aria-invalid="Boolean(keyProblem) || null"
        :aria-describedby="keyProblem ? `${id}-key-error` : null"
        @input="update({ key: $event.target.value })"
      />
    </div>

    <TypePicker
      v-if="allowTypeChange"
      :model-value="row.type"
      :disabled="readonly"
      @update:model-value="retype"
    />

    <ValueInput
      class="min-w-0 flex-1"
      :model-value="row.value"
      :type="row.type"
      :readonly="readonly"
      :invalid="Boolean(error)"
      @update:model-value="value => update({ value })"
    />

    <Button
      v-if="!readonly"
      type="button"
      icon="trash"
      variant="ghost"
      state="danger"
      size="small"
      padding="tight"
      class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
      :aria-label="__('Remove :key', { key: row.key || __('row') })"
      @click="$emit('remove')"
    />
  </div>

  <p v-if="keyProblem" :id="`${id}-key-error`" class="px-2 pb-1 text-xs text-red-500">
    {{ keyProblem }}
  </p>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import DragHandle from '../ui/DragHandle'
import TypePicker from './TypePicker'
import ValueInput from './ValueInput'
import { blankFor } from '../../support/json'
import { uid } from '../../support/uid'
import { allowDrop, beginDrag, endDrag, resolveDrop } from '../../support/dragReorder'

export default {
  name: 'KeyValueRow',

  components: { Button, DragHandle, TypePicker, ValueInput },

  props: {
    row: { type: Object, required: true },
    index: { type: Number, required: true },
    scope: { type: String, default: 'keyvalue' },
    readonly: { type: Boolean, default: false },
    allowTypeChange: { type: Boolean, default: true },
    duplicate: { type: Boolean, default: false },
    error: { type: String, default: null },
  },

  emits: ['update:row', 'remove', 'reorder'],

  data: () => ({ id: uid('njf-kv') }),

  methods: {
    update(patch) {
      this.$emit('update:row', { ...this.row, ...patch })
    },

    retype(type) {
      this.update({ type, value: blankFor(type, this.row.value) })
    },

    onDragStart(event) {
      beginDrag(event, { scope: this.scope, index: this.index })
    },

    onDragEnd() {
      endDrag()
    },

    onDragOver(event) {
      allowDrop(event, this.scope)
    },

    onDrop() {
      const move = resolveDrop(this.scope, this.index)

      if (move) this.$emit('reorder', move)
    },
  },

  computed: {
    /**
     * Nova's own KeyValue silently drops blank-key rows on submit and lets a
     * duplicate key quietly overwrite its twin. Say so instead.
     */
    keyProblem() {
      if (this.row.key === '') return this.__('A key is required; this row will not be saved.')
      if (this.duplicate) return this.__('Duplicate key; only the last one will be saved.')

      return this.error
    },
  },
}
</script>
