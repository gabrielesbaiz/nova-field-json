<template>
  <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div
      class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
    >
      <span class="w-40 shrink-0 text-xxs font-bold uppercase tracking-wide text-gray-500">
        {{ __('Key') }}
      </span>
      <span class="text-xxs font-bold uppercase tracking-wide text-gray-500">
        {{ __('Value') }}
      </span>
      <span class="ml-auto text-xxs text-gray-400 dark:text-gray-500">
        {{ __(':count keys', { count: rows.length }) }}
      </span>
    </div>

    <EmptyState v-if="rows.length === 0" :readonly="readonly" @create="() => addRow()" />

    <div v-else @keydown="onKeydown">
      <KeyValueRow
        v-for="(row, index) in rows"
        :key="row.id"
        :row="row"
        :index="index"
        :readonly="readonly"
        :allow-type-change="allowTypeChange"
        :duplicate="duplicates.has(row.key) && row.key !== ''"
        :error="errorFor(row.key)"
        @update:row="next => replaceRow(index, next)"
        @remove="removeRow(index)"
        @reorder="reorder"
      />
    </div>

    <div
      v-if="!readonly && rows.length > 0"
      class="flex items-center gap-2 border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
    >
      <Button type="button" variant="link" size="small" leading-icon="plus-circle" @click="addRow()">
        {{ __('Add row') }}
      </Button>
      <span class="text-xxs text-gray-400 dark:text-gray-500">
        {{ __('Enter on the last value adds a row.') }}
      </span>
    </div>
  </div>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import KeyValueRow from './KeyValueRow'
import EmptyState from '../ui/EmptyState'
import { coerce, typeOf } from '../../support/json'
import { moveAt } from '../../support/paths'
import { errorForPath } from '../../support/errors'
import { uid } from '../../support/uid'

export default {
  name: 'KeyValueEditor',

  components: { Button, EmptyState, KeyValueRow },

  props: {
    modelValue: { default: () => ({}) },
    readonly: { type: Boolean, default: false },
    allowTypeChange: { type: Boolean, default: true },
    errors: { type: Object, default: null },
    validationKey: { type: String, default: '' },
  },

  emits: ['update:modelValue'],

  data() {
    return { rows: this.toRows(this.modelValue) }
  },

  watch: {
    modelValue(next) {
      // Only re-seed when the change came from outside, or every keystroke
      // would rebuild the rows and lose focus.
      if (JSON.stringify(this.toObject(this.rows)) !== JSON.stringify(next)) {
        this.rows = this.toRows(next)
      }
    },
  },

  methods: {
    toRows(value) {
      if (value === null || typeof value !== 'object') return []

      return Object.entries(value).map(([key, item]) => ({
        id: uid('njf-row'),
        key,
        value: item,
        type: typeOf(item),
      }))
    },

    /** Last write wins, matching how a JSON object behaves. */
    toObject(rows) {
      const out = {}

      rows.forEach(row => {
        if (row.key === '') return

        out[row.key] = coerce(row.value, row.type)
      })

      return out
    },

    emit() {
      this.$emit('update:modelValue', this.toObject(this.rows))
    },

    replaceRow(index, next) {
      this.rows = this.rows.map((row, i) => (i === index ? next : row))
      this.emit()
    },

    removeRow(index) {
      this.rows = this.rows.filter((_, i) => i !== index)
      this.emit()
    },

    addRow(after = null) {
      const row = { id: uid('njf-row'), key: '', value: '', type: 'string' }
      const at = after === null ? this.rows.length : after + 1

      this.rows = [...this.rows.slice(0, at), row, ...this.rows.slice(at)]

      this.$nextTick(() => {
        const inputs = this.$el.querySelectorAll('input[id$="-key"]')

        inputs[at]?.focus()
      })
    },

    reorder({ from, to }) {
      this.rows = moveAt({ rows: this.rows }, ['rows'], from, to).rows
      this.emit()
    },

    onKeydown(event) {
      if (this.readonly) return

      const row = event.target.closest('[class*="group"]')
      const index = row ? [...row.parentElement.children].indexOf(row) : -1

      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault()
        // Nova's KeyValue only adds rows from the button, so filling one in
        // means reaching for the mouse on every single row.
        this.addRow(index)
      }

      if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
        event.preventDefault()

        const to = index + (event.key === 'ArrowUp' ? -1 : 1)

        if (index >= 0 && to >= 0 && to < this.rows.length) {
          this.reorder({ from: index, to })
        }
      }

      if ((event.metaKey || event.ctrlKey) && event.key === 'Backspace' && index >= 0) {
        event.preventDefault()
        this.removeRow(index)
      }
    },

    errorFor(key) {
      return errorForPath(this.errors, this.validationKey, [key])
    },
  },

  computed: {
    duplicates() {
      const seen = new Set()
      const dupes = new Set()

      this.rows.forEach(row => {
        if (seen.has(row.key)) dupes.add(row.key)

        seen.add(row.key)
      })

      return dupes
    },
  },
}
</script>
