<template>
  <PanelItem :index="index" :field="field">
    <template #value>
      <div v-if="isEmpty" class="text-gray-400 dark:text-gray-500">&mdash;</div>

      <div
        v-else
        class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
      >
        <div
          class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
        >
          <JsonBadge :value="decoded" />

          <div class="ml-auto flex items-center gap-1">
            <Button type="button" variant="ghost" size="small" padding="tight" @click="toggleAll">
              {{ allExpanded ? __('Collapse all') : __('Expand all') }}
            </Button>
            <Button
              type="button" variant="ghost" size="small" padding="tight" icon="clipboard"
              :aria-label="__('Copy JSON to clipboard')"
              @click="copy"
            />
          </div>
        </div>

        <div class="max-h-[28rem] overflow-auto p-3">
          <JsonViewer
            :value="decoded"
            :expand-signal="expandSignal"
            :initially-expanded="field.expandDepth ?? 2"
          />
        </div>
      </div>
    </template>
  </PanelItem>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import JsonViewer from './viewer/JsonViewer'
import JsonBadge from './viewer/JsonBadge'
import { decode, isEmptyValue, stringify } from '../support/json'

export default {
  name: 'JsonEditorDetailField',

  components: { Button, JsonBadge, JsonViewer },

  props: ['index', 'resource', 'resourceName', 'resourceId', 'field'],

  data: () => ({ allExpanded: false, expandSignal: 0 }),

  methods: {
    toggleAll() {
      this.allExpanded = !this.allExpanded
      this.expandSignal += this.allExpanded ? 1 : -1
    },

    async copy() {
      try {
        await navigator.clipboard.writeText(stringify(this.decoded, 2))
        Nova.success(this.__('Copied to clipboard.'))
      } catch (e) {
        Nova.error(this.__('Could not copy to clipboard.'))
      }
    },
  },

  computed: {
    decoded() {
      return decode(this.field.value, null)
    },

    isEmpty() {
      return isEmptyValue(this.decoded)
    },
  },
}
</script>
