<template>
  <div class="space-y-2">
    <div
      v-if="rows.length === 0"
      class="rounded-lg border-4 border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-900"
    >
      <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No rows yet.') }}</p>
    </div>

    <RepeatableRow
      v-for="(row, index) in rows"
      :key="row.id"
      ref="rows"
      :fields="row.fields"
      :index="index"
      :total="rows.length"
      :via-parent="validationKey"
      :resource-name="resourceName"
      :resource-id="resourceId"
      :readonly="readonly"
      :sortable="sortable"
      :can-remove="canRemove"
      :errors="errors"
      @remove="removeRow"
      @reorder="reorder"
    />

    <div class="flex items-center gap-2">
      <Button
        v-if="!readonly"
        type="button"
        variant="link"
        size="small"
        leading-icon="plus-circle"
        :disabled="!canAdd"
        @click="addRow"
      >
        {{ __('Add row') }}
      </Button>

      <!-- Nova's Repeater has no min/max at all; show the budget rather than
           only disabling the button. -->
      <span v-if="min !== null || max !== null" class="text-xxs text-gray-400 dark:text-gray-500">
        {{ counter }}
      </span>
    </div>

    <div role="status" aria-live="polite" class="sr-only">{{ announcement }}</div>
  </div>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import RepeatableRow from './RepeatableRow'
import { moveAt } from '../../support/paths'
import { uid } from '../../support/uid'

export default {
  name: 'RepeatableRows',

  components: { Button, RepeatableRow },

  props: {
    field: { type: Object, required: true },
    validationKey: { type: String, required: true },
    resourceName: { type: String, default: null },
    resourceId: { type: [String, Number], default: null },
    readonly: { type: Boolean, default: false },
    errors: { type: Object, default: null },
  },

  data() {
    return { rows: [], announcement: '' }
  },

  created() {
    this.rows = (this.field.value || []).map(row => ({
      id: uid('njf-repeat'),
      fields: this.cloneTemplate(row.fields),
    }))

    while (this.rows.length < (this.min ?? 0)) {
      this.rows.push({ id: uid('njf-repeat'), fields: this.cloneTemplate() })
    }
  },

  methods: {
    /**
     * Each row needs its own field objects: they carry a mutable `value` and
     * get a `fill` bound to them in mounted(), so sharing one set across rows
     * makes every row render and submit the last row's data.
     */
    cloneTemplate(fields = null) {
      return JSON.parse(JSON.stringify(fields || this.field.rowTemplate || []))
    },

    addRow() {
      if (!this.canAdd) return

      this.rows = [...this.rows, { id: uid('njf-repeat'), fields: this.cloneTemplate() }]
      this.announce(this.__('Row added'))
    },

    removeRow(index) {
      if (!this.canRemove) return

      this.rows = this.rows.filter((_, i) => i !== index)
      this.announce(this.__('Row :number removed', { number: index + 1 }))
    },

    reorder({ from, to }) {
      if (to < 0 || to >= this.rows.length) return

      this.rows = moveAt({ rows: this.rows }, ['rows'], from, to).rows
      this.announce(this.__('Moved to position :position', { position: to + 1 }))
    },

    announce(message) {
      this.announcement = message
    },

    /**
     * Post as `attribute[i][fields][key]`.
     *
     * The `[fields]` segment is load-bearing, not cosmetic: it is what makes
     * Laravel report a row error as `tiers.0.fields.price`, which is exactly
     * the key the nested field looks itself up under.
     */
    fillRows(formData, attribute) {
      ;(this.$refs.rows || []).forEach((row, index) => {
        const values = row.collect()

        Object.keys(values).forEach(key => {
          formData.append(`${attribute}[${index}][fields][${key}]`, values[key])
        })
      })
    },
  },

  computed: {
    min() {
      return this.field.min ?? null
    },

    max() {
      return this.field.max ?? null
    },

    sortable() {
      return Boolean(this.field.sortableRows)
    },

    canAdd() {
      return this.max === null || this.rows.length < this.max
    },

    canRemove() {
      return this.min === null || this.rows.length > this.min
    },

    counter() {
      if (this.max !== null) {
        return this.__(':count of :max', { count: this.rows.length, max: this.max })
      }

      return this.__('Minimum :min', { min: this.min })
    },
  },
}
</script>
