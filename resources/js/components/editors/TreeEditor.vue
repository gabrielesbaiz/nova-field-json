<template>
  <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div
      class="flex items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
    >
      <span class="text-xxs font-bold uppercase tracking-wide text-gray-500">
        {{ __('Structure') }}
      </span>
      <span class="ml-auto hidden text-xxs text-gray-400 sm:inline dark:text-gray-500">
        {{ __('Arrow keys navigate. Alt + Up / Down reorders.') }}
      </span>
    </div>

    <EmptyState v-if="isEmpty" :readonly="readonly" @create="createRoot" />

    <ul
      v-else
      ref="tree"
      role="tree"
      class="m-0 select-none py-1"
      :aria-label="label"
      @keydown="onKeydown"
    >
      <TreeNode
        v-for="child in children"
        :key="pathKey(child.path)"
        :node="child"
        :setsize="children.length"
        :in-array="rootIsArray"
        :readonly="readonly"
        :allow-type-change="allowTypeChange"
        :locked-keys="lockedKeys"
        :expanded="expanded"
        :focused-path="focusedPath"
        :errors="errors"
        :validation-key="validationKey"
        @toggle="toggle"
        @focus-path="focusedPath = $event"
        @patch="applyPatch"
      />
    </ul>

    <div
      v-if="!readonly && !isEmpty"
      class="flex items-center gap-2 border-t border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-800"
    >
      <Button type="button" variant="link" size="small" leading-icon="plus-circle" @click="addToRoot">
        {{ rootIsArray ? __('Add item') : __('Add key') }}
      </Button>
    </div>

    <!-- Drag and keyboard reordering are invisible to a screen reader
         otherwise; Nova's own repeater announces nothing at all. -->
    <div role="status" aria-live="polite" class="sr-only">{{ announcement }}</div>
  </div>
</template>

<script>
import { Button } from 'laravel-nova-ui'
import TreeNode from './TreeNode'
import EmptyState from '../ui/EmptyState'
import { blankFor, isContainer, isEmptyValue, typeOf } from '../../support/json'
import {
  childPaths, getAt, deleteAt, keyToPath, moveAt, pathKey, renameKeyAt, setAt, uniqueKey,
} from '../../support/paths'

export default {
  name: 'TreeEditor',

  components: { Button, EmptyState, TreeNode },

  props: {
    modelValue: { default: () => ({}) },
    readonly: { type: Boolean, default: false },
    allowTypeChange: { type: Boolean, default: true },
    lockedKeys: { type: Array, default: () => [] },
    errors: { type: Object, default: null },
    validationKey: { type: String, default: '' },
    expandDepth: { type: Number, default: 1 },
    label: { type: String, default: 'JSON structure' },
  },

  emits: ['update:modelValue'],

  data() {
    return { expanded: new Set(), focusedPath: null, announcement: '' }
  },

  created() {
    this.expandToDepth(this.modelValue, [], this.expandDepth)
  },

  methods: {
    pathKey,

    expandToDepth(node, path, remaining) {
      if (remaining <= 0 || !isContainer(node)) return

      if (path.length) this.expanded.add(pathKey(path))

      childPaths(node, path).forEach(child => {
        this.expandToDepth(child.value, child.path, remaining - 1)
      })
    },

    toggle(id) {
      const next = new Set(this.expanded)

      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }

      this.expanded = next
    },

    /**
     * Every mutation funnels through one reducer, so the keyboard, the drag
     * handles and the buttons cannot drift apart, and every change produces a
     * whole new value rather than an in-place edit.
     */
    applyPatch(patch) {
      if (this.readonly) return

      let next = this.modelValue

      switch (patch.op) {
        case 'set':
          next = setAt(next, patch.path, patch.value)
          break

        case 'retype':
          next = setAt(next, patch.path, blankFor(patch.type, getAt(next, patch.path)))
          break

        case 'rename': {
          next = renameKeyAt(next, patch.path, patch.key)

          const from = pathKey(patch.path)
          const to = pathKey([...patch.path.slice(0, -1), patch.key])

          if (this.expanded.has(from)) {
            const moved = new Set(this.expanded)
            moved.delete(from)
            moved.add(to)
            this.expanded = moved
          }

          this.focusedPath = to
          break
        }

        case 'add': {
          const container = getAt(next, patch.path)

          if (Array.isArray(container)) {
            next = setAt(next, patch.path, [...container, blankFor(patch.type)])
          } else {
            const key = uniqueKey(container ?? {}, patch.key || 'key')

            next = setAt(next, patch.path, { ...(container ?? {}), [key]: blankFor(patch.type) })
          }

          if (patch.path.length) {
            this.expanded = new Set(this.expanded).add(pathKey(patch.path))
          }
          break
        }

        case 'remove': {
          const index = this.visiblePaths.indexOf(pathKey(patch.path))

          next = deleteAt(next, patch.path)

          this.announce(this.__('Removed :key', { key: patch.path[patch.path.length - 1] }))
          this.$nextTick(() => this.focusIndex(Math.max(0, index - 1)))
          break
        }

        case 'move': {
          const parent = patch.path.slice(0, -1)

          next = moveAt(next, parent, patch.from, patch.to)

          this.announce(
            this.__('Moved to position :position', { position: patch.to + 1 })
          )
          break
        }

        default:
          return
      }

      this.$emit('update:modelValue', next)
    },

    createRoot(type) {
      this.$emit('update:modelValue', blankFor(type === 'string' ? 'string' : type))
    },

    addToRoot() {
      this.applyPatch({ op: 'add', path: [], type: 'string' })
    },

    announce(message) {
      this.announcement = message
    },

    onKeydown(event) {
      // Let the inline inputs own plain typing; only act when the event came
      // from the row itself.
      if (event.target.getAttribute('role') !== 'treeitem') return

      const paths = this.visiblePaths
      const index = paths.indexOf(this.focusedPath)

      if (event.altKey && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
        event.preventDefault()
        this.moveFocused(event.key === 'ArrowUp' ? -1 : 1)

        return
      }

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault()
          this.focusIndex(Math.min(paths.length - 1, index + 1))
          break

        case 'ArrowUp':
          event.preventDefault()
          this.focusIndex(Math.max(0, index - 1))
          break

        case 'ArrowRight': {
          event.preventDefault()

          if (this.isBranch(this.focusedPath) && !this.expanded.has(this.focusedPath)) {
            this.toggle(this.focusedPath)
          } else {
            this.focusIndex(Math.min(paths.length - 1, index + 1))
          }
          break
        }

        case 'ArrowLeft': {
          event.preventDefault()

          if (this.isBranch(this.focusedPath) && this.expanded.has(this.focusedPath)) {
            this.toggle(this.focusedPath)
          } else {
            const parent = pathKey(keyToPath(this.focusedPath).slice(0, -1))

            if (parent) this.focusPath(parent)
          }
          break
        }

        case 'Home':
          event.preventDefault()
          this.focusIndex(0)
          break

        case 'End':
          event.preventDefault()
          this.focusIndex(paths.length - 1)
          break

        case 'Delete':
        case 'Backspace':
          if (this.readonly || !this.focusedPath) return

          event.preventDefault()
          this.applyPatch({ op: 'remove', path: keyToPath(this.focusedPath) })
          break

        case 'Enter': {
          if (this.readonly) return

          event.preventDefault()

          // Shift+Enter adds a sibling, Ctrl/Cmd+Enter adds a child.
          if (event.shiftKey) {
            this.applyPatch({ op: 'add', path: keyToPath(this.focusedPath).slice(0, -1), type: 'string' })
          } else if (event.metaKey || event.ctrlKey) {
            if (this.isBranch(this.focusedPath)) {
              this.applyPatch({ op: 'add', path: keyToPath(this.focusedPath), type: 'string' })
            }
          } else {
            this.editFocused()
          }
          break
        }

        default:
          break
      }
    },

    focusPath(id) {
      this.focusedPath = id

      this.$nextTick(() => {
        this.$refs.tree?.querySelector(`[data-path="${window.CSS.escape(id)}"]`)?.focus()
      })
    },

    focusIndex(index) {
      const id = this.visiblePaths[index]

      if (id) this.focusPath(id)
    },

    editFocused() {
      this.$nextTick(() => {
        const row = this.$refs.tree?.querySelector(`[data-path="${window.CSS.escape(this.focusedPath)}"]`)

        row?.querySelector('input, select')?.focus()
      })
    },

    moveFocused(delta) {
      if (this.readonly || !this.focusedPath) return

      const path = keyToPath(this.focusedPath)
      const parent = path.slice(0, -1)
      const container = getAt(this.modelValue, parent)

      if (!isContainer(container)) return

      const keys = Array.isArray(container)
        ? container.map((_, i) => String(i))
        : Object.keys(container)

      const from = keys.indexOf(path[path.length - 1])
      const to = from + delta

      if (to < 0 || to >= keys.length) return

      this.applyPatch({ op: 'move', path, from, to })

      this.focusedPath = pathKey([
        ...parent,
        Array.isArray(container) ? String(to) : keys[from],
      ])
    },

    isBranch(id) {
      return id ? isContainer(getAt(this.modelValue, keyToPath(id))) : false
    },
  },

  computed: {
    isEmpty() {
      return isEmptyValue(this.modelValue) && typeOf(this.modelValue) !== 'string'
    },

    rootIsArray() {
      return Array.isArray(this.modelValue)
    },

    children() {
      return childPaths(this.modelValue, [])
    },

    /** Flat, in-DOM-order list of focusable rows; drives the roving tabindex. */
    visiblePaths() {
      const out = []

      const walk = (node, path) => {
        childPaths(node, path).forEach(child => {
          const id = pathKey(child.path)

          out.push(id)

          if (this.expanded.has(id)) walk(child.value, child.path)
        })
      }

      walk(this.modelValue, [])

      return out
    },
  },
}
</script>
