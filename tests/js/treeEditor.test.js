import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import TreeEditor from '../../resources/js/components/editors/TreeEditor.vue'
import { pathKey } from '../../resources/js/support/paths'

function mountTree(modelValue, props = {}) {
  return mount(TreeEditor, {
    props: { modelValue, expandDepth: 5, ...props },
  })
}

/** The latest value the editor emitted. */
function emitted(wrapper) {
  const events = wrapper.emitted('update:modelValue')

  return events ? events[events.length - 1][0] : undefined
}

describe('TreeEditor patch reducer', () => {
  let wrapper

  beforeEach(() => {
    wrapper = mountTree({ a: 1, b: { c: 'x' }, list: [1, 2] })
  })

  it('sets a nested value without touching its siblings', () => {
    wrapper.vm.applyPatch({ op: 'set', path: ['b', 'c'], value: 'y' })

    expect(emitted(wrapper)).toEqual({ a: 1, b: { c: 'y' }, list: [1, 2] })
  })

  it('retypes a value to a sensible blank', () => {
    wrapper.vm.applyPatch({ op: 'retype', path: ['a'], type: 'string' })

    expect(emitted(wrapper).a).toBe('1')
  })

  it('renames a key in place', () => {
    wrapper.vm.applyPatch({ op: 'rename', path: ['a'], key: 'alpha' })

    expect(Object.keys(emitted(wrapper))).toEqual(['alpha', 'b', 'list'])
  })

  it('adds a uniquely named key to an object', () => {
    wrapper.vm.applyPatch({ op: 'add', path: [], key: 'a', type: 'string' })

    expect(Object.keys(emitted(wrapper))).toContain('a_2')
  })

  it('appends to an array rather than naming an index', () => {
    wrapper.vm.applyPatch({ op: 'add', path: ['list'], type: 'number' })

    expect(emitted(wrapper).list).toEqual([1, 2, 0])
  })

  it('removes a node', () => {
    wrapper.vm.applyPatch({ op: 'remove', path: ['b'] })

    expect(emitted(wrapper)).toEqual({ a: 1, list: [1, 2] })
  })

  it('reorders siblings', () => {
    wrapper.vm.applyPatch({ op: 'move', path: ['list', '0'], from: 0, to: 1 })

    expect(emitted(wrapper).list).toEqual([2, 1])
  })

  it('emits nothing at all when readonly', () => {
    const readonlyTree = mountTree({ a: 1 }, { readonly: true })

    readonlyTree.vm.applyPatch({ op: 'set', path: ['a'], value: 2 })

    expect(readonlyTree.emitted('update:modelValue')).toBeUndefined()
  })

  it('follows a renamed key with the expansion state and the focus', () => {
    wrapper.vm.focusedPath = pathKey(['b'])
    wrapper.vm.applyPatch({ op: 'rename', path: ['b'], key: 'beta' })

    expect(wrapper.vm.focusedPath).toBe(pathKey(['beta']))
    expect(wrapper.vm.expanded.has(pathKey(['beta']))).toBe(true)
    expect(wrapper.vm.expanded.has(pathKey(['b']))).toBe(false)
  })
})

describe('TreeEditor navigation', () => {
  it('lists visible paths in DOM order, honouring collapse', () => {
    const wrapper = mountTree({ a: { b: 1 }, c: 2 }, { expandDepth: 5 })

    expect(wrapper.vm.visiblePaths).toEqual([
      pathKey(['a']),
      pathKey(['a', 'b']),
      pathKey(['c']),
    ])

    wrapper.vm.toggle(pathKey(['a']))

    expect(wrapper.vm.visiblePaths).toEqual([pathKey(['a']), pathKey(['c'])])
  })

  it('reorders the focused node with Alt + Arrow', () => {
    const wrapper = mountTree({ a: 1, b: 2, c: 3 })
    wrapper.vm.focusedPath = pathKey(['c'])

    wrapper.vm.moveFocused(-1)

    expect(Object.keys(emitted(wrapper))).toEqual(['a', 'c', 'b'])
  })

  it('will not move past either end', () => {
    const wrapper = mountTree({ a: 1, b: 2 })
    wrapper.vm.focusedPath = pathKey(['a'])

    wrapper.vm.moveFocused(-1)

    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })
})

describe('TreeEditor empty state', () => {
  it('asks which root type to create instead of guessing', () => {
    const wrapper = mountTree({})

    expect(wrapper.text()).toContain('Start this value as:')

    wrapper.vm.createRoot('array')

    expect(emitted(wrapper)).toEqual([])
  })
})

describe('TreeEditor rendering', () => {
  it('exposes the ARIA tree contract', () => {
    const wrapper = mountTree({ a: { b: 1 } })

    expect(wrapper.find('[role="tree"]').exists()).toBe(true)
    expect(wrapper.find('[role="group"]').exists()).toBe(true)

    const items = wrapper.findAll('[role="treeitem"]')

    expect(items.length).toBe(2)
    expect(items[0].attributes('aria-level')).toBe('1')
    expect(items[0].attributes('aria-expanded')).toBe('true')
    expect(items[1].attributes('aria-level')).toBe('2')
  })

  it('keeps exactly one row in the tab order', async () => {
    const wrapper = mountTree({ a: 1, b: 2 })
    wrapper.vm.focusedPath = pathKey(['a'])
    await wrapper.vm.$nextTick()

    const tabbable = wrapper
      .findAll('[role="treeitem"]')
      .filter(item => item.attributes('tabindex') === '0')

    expect(tabbable.length).toBe(1)
  })

  it('renders nested containers, which Nova KeyValue drops entirely', () => {
    // KeyValueItem has `v-if="isNotObject"`, so a row whose value is an object
    // renders nothing at all.
    const wrapper = mountTree({ nested: { deep: { deeper: 1 } } })

    expect(wrapper.findAll('[role="treeitem"]').length).toBe(3)
  })
})
