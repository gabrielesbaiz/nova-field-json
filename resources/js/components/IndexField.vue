<template>
  <div :class="`text-${field.textAlign}`">
    <p v-if="isEmpty" class="text-gray-400 dark:text-gray-500">&mdash;</p>

    <Tooltip v-else placement="bottom-start">
      <!-- The row itself is a link; stop the click so previewing does not
           navigate away. -->
      <button
        type="button"
        class="rounded focus:outline-none focus:ring-2 focus:ring-primary-500"
        :aria-label="__('Preview JSON value')"
        @click.stop.prevent
      >
        <JsonBadge :value="decoded" />
      </button>

      <template #content>
        <pre
          class="max-h-64 max-w-sm overflow-auto whitespace-pre-wrap break-all p-1 font-mono text-xs leading-relaxed"
        >{{ preview }}</pre>
      </template>
    </Tooltip>
  </div>
</template>

<script>
import JsonBadge from './viewer/JsonBadge'
import { decode, isEmptyValue, stringify } from '../support/json'

export default {
  name: 'JsonEditorIndexField',

  components: { JsonBadge },

  props: ['resourceName', 'field', 'resource'],

  computed: {
    decoded() {
      return decode(this.field.value, null)
    },

    isEmpty() {
      return isEmptyValue(this.decoded)
    },

    preview() {
      const text = stringify(this.decoded, 2)

      return text.length > 2000 ? `${text.slice(0, 2000)}…` : text
    },
  },
}
</script>
