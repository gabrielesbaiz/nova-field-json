<template>
  <div
    class="flex items-start gap-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 dark:border-red-500/40 dark:bg-red-500/10"
    role="alert"
  >
    <Icon name="exclamation-triangle" type="micro" class="mt-0.5 shrink-0 text-red-500" />

    <div class="min-w-0 flex-1">
      <p class="text-sm font-semibold text-red-700 dark:text-red-400">
        {{ __('Line :line, column :column', { line: error.line, column: error.column }) }}
      </p>
      <p class="mt-0.5 break-words font-mono text-xs text-red-600 dark:text-red-400">
        {{ error.message }}
      </p>
    </div>

    <Button
      v-if="canLocate"
      type="button"
      variant="link"
      size="small"
      state="danger"
      @click="$emit('locate', error)"
    >
      {{ __('Go to error') }}
    </Button>
  </div>
</template>

<script>
import { Button, Icon } from 'laravel-nova-ui'

export default {
  name: 'ParseErrorBanner',

  components: { Button, Icon },

  props: {
    error: { type: Object, required: true },
    canLocate: { type: Boolean, default: false },
  },

  emits: ['locate'],
}
</script>
