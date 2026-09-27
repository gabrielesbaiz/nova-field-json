<template>
  <span
    class="shrink-0 cursor-grab select-none px-1 text-gray-300 opacity-0 transition-opacity active:cursor-grabbing group-hover:opacity-100 group-focus-within:opacity-100 dark:text-gray-600"
    :class="{ 'opacity-100': dragging }"
    draggable="true"
    aria-hidden="true"
    @dragstart="$emit('dragstart', $event)"
    @dragend="$emit('dragend', $event)"
  >
    &#8942;&#8942;
  </span>
</template>

<script>
export default {
  name: 'DragHandle',

  // Keyboard users reorder with Alt + Arrow, so the handle itself is hidden
  // from assistive technology rather than being a second, worse affordance.
  props: {
    dragging: { type: Boolean, default: false },
  },

  emits: ['dragstart', 'dragend'],
}
</script>
