<template>
  <ul class="font-mono text-xs leading-relaxed" :class="depth === 0 ? 'm-0 p-0' : 'm-0 border-l border-gray-100 pl-3 dark:border-gray-700'">
    <li v-for="child in children" :key="child.key" class="list-none py-0.5">
      <div class="flex items-start gap-1.5">
        <button
          v-if="isContainer(child.value)"
          type="button"
          class="-ml-1 shrink-0 rounded text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:text-gray-300"
          :aria-expanded="isOpen(child.key)"
          :aria-label="isOpen(child.key) ? __('Collapse') : __('Expand')"
          @click="toggle(child.key)"
        >
          <Icon :name="isOpen(child.key) ? 'chevron-down' : 'chevron-right'" type="micro" />
        </button>
        <span v-else class="w-4 shrink-0" aria-hidden="true" />

        <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ child.key }}:</span>

        <JsonBadge v-if="isContainer(child.value)" :value="child.value" />
        <span v-else class="break-all" :class="scalarClasses(child.value)">{{ display(child.value) }}</span>
      </div>

      <JsonViewer
        v-if="isContainer(child.value) && isOpen(child.key)"
        :value="child.value"
        :depth="depth + 1"
        :initially-expanded="initiallyExpanded"
        :expand-signal="expandSignal"
      />
    </li>
  </ul>
</template>

<script>
import { Icon } from 'laravel-nova-ui'
import JsonBadge from './JsonBadge'
import { isContainer, typeOf } from '../../support/json'
import { childPaths } from '../../support/paths'

export default {
  name: 'JsonViewer',

  components: { Icon, JsonBadge },

  props: {
    value: { default: null },
    depth: { type: Number, default: 0 },
    initiallyExpanded: { type: Number, default: 2 },
    /** Incremented to expand everything, decremented to collapse everything. */
    expandSignal: { type: Number, default: 0 },
  },

  data() {
    return { open: {}, forced: null }
  },

  watch: {
    expandSignal(next, previous) {
      this.forced = next > previous
      this.open = {}
    },
  },

  methods: {
    isContainer,

    isOpen(key) {
      if (this.forced !== null && this.open[key] === undefined) return this.forced

      return this.open[key] ?? this.depth < this.initiallyExpanded
    },

    toggle(key) {
      this.open = { ...this.open, [key]: !this.isOpen(key) }
    },

    display(value) {
      return typeof value === 'string' ? JSON.stringify(value) : String(value)
    },

    scalarClasses(value) {
      switch (typeOf(value)) {
        case 'number':
          return 'text-sky-600 dark:text-sky-400'
        case 'boolean':
          return 'text-violet-600 dark:text-violet-400'
        case 'null':
          return 'text-gray-400 dark:text-gray-500'
        default:
          return 'text-emerald-700 dark:text-emerald-400'
      }
    },
  },

  computed: {
    children() {
      return childPaths(this.value, [])
    },
  },
}
</script>
