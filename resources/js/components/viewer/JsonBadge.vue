<template>
  <span
    class="inline-flex items-center rounded-full px-2 py-0.5 font-mono text-xxs font-bold"
    :class="classes"
  >
    {{ summary.label }}
    <span class="sr-only">{{ description }}</span>
  </span>
</template>

<script>
import { summarize, typeOf } from '../../support/json'
import { typeClasses } from '../../support/tokens'

export default {
  name: 'JsonBadge',

  props: {
    value: { default: null },
  },

  computed: {
    summary() {
      return summarize(this.value)
    },

    classes() {
      return typeClasses(typeOf(this.value))
    },

    /** The badge itself is glyphic; give a screen reader real words. */
    description() {
      const { type, count } = this.summary

      if (type === 'object') return ` ${count} keys`
      if (type === 'array') return ` ${count} items`

      return ` ${type}`
    },
  },
}
</script>
