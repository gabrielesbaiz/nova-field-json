<template>
  <li role="none">
    <div
      role="treeitem"
      class="group flex items-center gap-1.5 rounded pr-2 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500"
      :class="rowClasses"
      :style="{ paddingLeft: `${node.path.length * 16}px` }"
      :tabindex="isFocused ? 0 : -1"
      :data-path="pathId"
      :aria-level="node.path.length"
      :aria-posinset="node.index + 1"
      :aria-setsize="setsize"
      :aria-expanded="branch ? expanded.has(pathId) : null"
      :aria-selected="isFocused"
      :aria-describedby="error ? `${pathId}-error` : null"
      @focus="$emit('focus-path', pathId)"
      @dragover="onDragOver"
      @drop.prevent="onDrop"
    >
      <DragHandle
        v-if="!readonly && sortable"
        @dragstart="onDragStart"
        @dragend="onDragEnd"
      />

      <button
        v-if="branch"
        type="button"
        tabindex="-1"
        class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        :aria-label="expanded.has(pathId) ? __('Collapse') : __('Expand')"
        @click.stop="$emit('toggle', pathId)"
      >
        <Icon :name="expanded.has(pathId) ? 'chevron-down' : 'chevron-right'" type="micro" />
      </button>
      <span v-else class="w-4 shrink-0" aria-hidden="true" />

      <!-- Array indices are positional, not names: renaming one is meaningless. -->
      <span
        v-if="inArray"
        class="shrink-0 font-mono text-xxs text-gray-400 dark:text-gray-500"
      >{{ node.key }}</span>
      <input
        v-else
        class="form-control form-input form-input-bordered h-7 w-32 shrink-0 py-0 font-mono text-xs"
        :value="keyDraft"
        :readonly="readonly || locked"
        :aria-label="__('Key')"
        @focus="keyBefore = keyDraft"
        @input="keyDraft = $event.target.value"
        @keydown.enter.prevent="commitKey"
        @keydown.esc.prevent="cancelKey"
        @blur="commitKey"
      />

      <TypePicker
        v-if="allowTypeChange"
        :model-value="type"
        :disabled="readonly || locked"
        @update:model-value="retype"
      />

      <Badge v-if="branch" :extra-classes="typeClasses(type)">{{ summary.label }}</Badge>
      <ValueInput
        v-else
        class="min-w-0 flex-1"
        :model-value="node.value"
        :type="type"
        :readonly="readonly"
        :invalid="Boolean(error)"
        @update:model-value="setValue"
      />

      <Button
        v-if="!readonly && !locked"
        type="button"
        tabindex="-1"
        icon="trash"
        variant="ghost"
        state="danger"
        size="small"
        padding="tight"
        class="ml-auto opacity-0 group-hover:opacity-100 group-focus-within:opacity-100"
        :aria-label="__('Remove :key', { key: node.key })"
        @click.stop="$emit('patch', { op: 'remove', path: node.path })"
      />
    </div>

    <p v-if="error" :id="`${pathId}-error`" class="pl-8 text-xs text-red-500">{{ error }}</p>

    <ul v-if="branch && expanded.has(pathId)" role="group" class="m-0 p-0">
      <TreeNode
        v-for="child in children"
        :key="pathKey(child.path)"
        :node="child"
        :setsize="children.length"
        :in-array="isArray"
        :readonly="readonly"
        :sortable="sortable"
        :allow-type-change="allowTypeChange"
        :locked-keys="lockedKeys"
        :expanded="expanded"
        :focused-path="focusedPath"
        :errors="errors"
        :validation-key="validationKey"
        @toggle="$emit('toggle', $event)"
        @focus-path="$emit('focus-path', $event)"
        @patch="$emit('patch', $event)"
      />
    </ul>
  </li>
</template>

<script>
import { Badge, Button, Icon } from 'laravel-nova-ui'
import DragHandle from '../ui/DragHandle'
import TypePicker from './TypePicker'
import ValueInput from './ValueInput'
import { isContainer, summarize, typeOf } from '../../support/json'
import { childPaths, pathKey } from '../../support/paths'
import { typeClasses } from '../../support/tokens'
import { errorForPath } from '../../support/errors'
import { allowDrop, beginDrag, endDrag, resolveDrop } from '../../support/dragReorder'

export default {
  name: 'TreeNode',

  components: { Badge, Button, DragHandle, Icon, TypePicker, ValueInput },

  props: {
    node: { type: Object, required: true },
    setsize: { type: Number, required: true },
    inArray: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    sortable: { type: Boolean, default: true },
    allowTypeChange: { type: Boolean, default: true },
    lockedKeys: { type: Array, default: () => [] },
    expanded: { type: Set, required: true },
    focusedPath: { type: String, default: null },
    errors: { type: Object, default: null },
    validationKey: { type: String, default: '' },
  },

  emits: ['toggle', 'focus-path', 'patch'],

  data() {
    return { keyDraft: this.node.key, keyBefore: null }
  },

  watch: {
    'node.key': function (next) {
      this.keyDraft = next
    },
  },

  methods: {
    pathKey,
    typeClasses,

    commitKey() {
      if (this.keyDraft === this.node.key) return

      if (this.keyDraft === '') {
        this.cancelKey()

        return
      }

      this.$emit('patch', { op: 'rename', path: this.node.path, key: this.keyDraft })
    },

    cancelKey() {
      this.keyDraft = this.keyBefore ?? this.node.key
    },

    retype(type) {
      this.$emit('patch', { op: 'retype', path: this.node.path, type })
    },

    setValue(value) {
      this.$emit('patch', { op: 'set', path: this.node.path, value })
    },

    onDragStart(event) {
      beginDrag(event, { scope: this.scope, index: this.node.index })
    },

    onDragEnd() {
      endDrag()
    },

    onDragOver(event) {
      allowDrop(event, this.scope)
    },

    onDrop() {
      const move = resolveDrop(this.scope, this.node.index)

      if (move) {
        this.$emit('patch', { op: 'move', path: this.node.path, ...move })
      }
    },
  },

  computed: {
    pathId() {
      return pathKey(this.node.path)
    },

    /** Only siblings may be reordered against each other. */
    scope() {
      return pathKey(this.node.path.slice(0, -1))
    },

    type() {
      return typeOf(this.node.value)
    },

    branch() {
      return isContainer(this.node.value)
    },

    isArray() {
      return Array.isArray(this.node.value)
    },

    summary() {
      return summarize(this.node.value)
    },

    children() {
      return childPaths(this.node.value, this.node.path)
    },

    isFocused() {
      return this.focusedPath === this.pathId
    },

    locked() {
      return this.lockedKeys.includes(this.node.path.join('.'))
    },

    error() {
      return errorForPath(this.errors, this.validationKey, this.node.path)
    },

    rowClasses() {
      return [
        this.error ? 'ring-1 ring-red-400 dark:ring-red-500' : '',
        this.isFocused
          ? 'bg-primary-50 dark:bg-gray-800'
          : 'hover:bg-gray-50 dark:hover:bg-gray-800',
      ]
    },
  },
}
</script>
