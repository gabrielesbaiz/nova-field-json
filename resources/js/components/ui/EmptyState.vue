<template>
  <div
    class="rounded-lg border-4 border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center dark:border-gray-600 dark:bg-gray-900"
  >
    <p class="text-sm text-gray-500 dark:text-gray-400">
      {{ readonly ? __('No value') : __('Start this value as:') }}
    </p>

    <div v-if="!readonly" class="mt-3 flex flex-wrap items-center justify-center gap-2">
      <!-- Ask which root type rather than guessing: a JSON column is just as
           often a list as it is an object, and guessing wrong costs the user
           two extra steps every time. -->
      <Button type="button" variant="outline" size="small" @click="$emit('create', 'object')">
        {{ __('Object') }} <span class="ml-1 font-mono text-xxs">{ }</span>
      </Button>
      <Button type="button" variant="outline" size="small" @click="$emit('create', 'array')">
        {{ __('Array') }} <span class="ml-1 font-mono text-xxs">[ ]</span>
      </Button>
      <Button type="button" variant="outline" size="small" @click="$emit('create', 'string')">
        {{ __('Value') }} <span class="ml-1 font-mono text-xxs">" "</span>
      </Button>
    </div>
  </div>
</template>

<script>
import { Button } from 'laravel-nova-ui'

export default {
  name: 'EmptyState',

  components: { Button },

  props: {
    readonly: { type: Boolean, default: false },
  },

  emits: ['create'],
}
</script>
