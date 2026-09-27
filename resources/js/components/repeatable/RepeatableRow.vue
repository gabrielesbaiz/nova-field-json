<template>
  <div
    class="group relative rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
    @dragover="onDragOver"
    @drop.prevent="onDrop"
  >
    <div
      class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-3 py-1.5 dark:border-gray-700 dark:bg-gray-800"
    >
      <DragHandle v-if="sortable && !readonly" @dragstart="onDragStart" @dragend="onDragEnd" />

      <span class="text-xxs font-bold uppercase tracking-wide text-gray-500">
        {{ __('Row :number', { number: index + 1 }) }}
      </span>

      <div class="ml-auto flex items-center gap-1">
        <Button
          v-if="sortable && !readonly"
          type="button" variant="ghost" size="small" padding="tight" icon="arrow-up"
          :disabled="index === 0"
          :aria-label="__('Move row up')"
          @click="$emit('reorder', { from: index, to: index - 1 })"
        />
        <Button
          v-if="sortable && !readonly"
          type="button" variant="ghost" size="small" padding="tight" icon="arrow-down"
          :disabled="index === total - 1"
          :aria-label="__('Move row down')"
          @click="$emit('reorder', { from: index, to: index + 1 })"
        />
        <Button
          v-if="!readonly"
          type="button" variant="ghost" state="danger" size="small" padding="tight" icon="trash"
          :disabled="!canRemove"
          :aria-label="__('Remove row :number', { number: index + 1 })"
          @click="remove"
        />
      </div>
    </div>

    <div class="divide-y divide-gray-100 dark:divide-gray-700">
      <component
        v-for="(field, fieldIndex) in fields"
        :is="`form-${field.component}`"
        :key="field.attribute"
        ref="fields"
        :field="field"
        :index="fieldIndex"
        :errors="errors"
        :nested="true"
        :resource-name="resourceName"
        :resource-id="resourceId"
        :show-help-text="true"
      />
    </div>
  </div>
</template>

<script>
import { computed } from 'vue'
import { Button } from 'laravel-nova-ui'
import DragHandle from '../ui/DragHandle'
import { allowDrop, beginDrag, endDrag, resolveDrop } from '../../support/dragReorder'

export default {
  name: 'RepeatableRow',

  components: { Button, DragHandle },

  props: {
    fields: { type: Array, required: true },
    index: { type: Number, required: true },
    total: { type: Number, required: true },
    scope: { type: String, default: 'repeatable' },
    viaParent: { type: String, required: true },
    resourceName: { type: String, default: null },
    resourceId: { type: [String, Number], default: null },
    readonly: { type: Boolean, default: false },
    sortable: { type: Boolean, default: false },
    canRemove: { type: Boolean, default: true },
    errors: { type: Object, default: null },
  },

  emits: ['remove', 'reorder'],

  provide() {
    // Nova's HandlesValidationErrors derives a nested field's error key as
    // `${viaParent}.${index}.fields.${attribute}`. Without these, per-row
    // validation messages resolve to nothing and silently fail to render.
    return {
      viaParent: computed(() => this.viaParent),
      index: computed(() => this.index),
      resourceName: computed(() => this.resourceName),
      resourceId: computed(() => this.resourceId),
      viaResource: computed(() => null),
      viaResourceId: computed(() => null),
      viaRelationship: computed(() => null),
      shownViaNewRelationModal: computed(() => false),
    }
  },

  methods: {
    /**
     * Nova's own repeater has this call commented out, so removing a row with
     * a pending file upload leaks it. Actually run it.
     */
    async remove() {
      await Promise.all(
        (this.$refs.fields || []).map(field => field.beforeRemove?.())
      )

      this.$emit('remove', this.index)
    },

    /** Collect this row's values by letting each field fill a scratch FormData. */
    collect() {
      const scratch = new FormData()

      ;(this.$refs.fields || []).forEach(field => field.fill?.(scratch))

      const values = {}

      for (const [key, value] of scratch.entries()) {
        values[key] = value
      }

      return values
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
}
</script>
