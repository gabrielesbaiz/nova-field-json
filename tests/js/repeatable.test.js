import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RepeatableRows from '../../resources/js/components/repeatable/RepeatableRows.vue'

/**
 * Stands in for a real Nova form field inside a row: it fills a scratch
 * FormData under its own un-namespaced attribute, exactly as Nova's do.
 */
const StubFormField = {
  name: 'StubFormField',
  props: ['field', 'errors', 'nested', 'resourceName', 'resourceId'],
  inject: ['viaParent', 'index'],
  mounted() {
    this.field.fill = this.fill
  },
  methods: {
    fill(formData) {
      formData.append(this.field.attribute, this.field.value ?? '')
    },
  },
  template: '<div class="stub-field" />',
}

function template() {
  return [
    { attribute: 'label', component: 'text-field', value: '' },
    { attribute: 'price', component: 'text-field', value: '' },
  ]
}

function mountRows(field = {}) {
  return mount(RepeatableRows, {
    props: {
      field: { rowTemplate: template(), value: [], ...field },
      validationKey: 'tiers',
      resourceName: 'products',
      resourceId: 1,
    },
    global: {
      components: { 'form-text-field': StubFormField },
    },
  })
}

function payload(wrapper, attribute = 'tiers') {
  const formData = new FormData()

  wrapper.vm.fillRows(formData, attribute)

  return [...formData.entries()]
}

describe('RepeatableRows fill shape', () => {
  it('posts attribute[i][fields][key]', () => {
    // The [fields] segment is not cosmetic. HandlesValidationErrors derives a
    // nested field's error key as `${viaParent}.${index}.fields.${attribute}`,
    // so posting tiers[0][label] would make Laravel report `tiers.0.label`
    // while the input looks itself up under `tiers.0.fields.label` -- and
    // every inline row error would silently render nowhere.
    const wrapper = mountRows({
      value: [
        { fields: [{ attribute: 'label', component: 'text-field', value: 'Small' }] },
        { fields: [{ attribute: 'label', component: 'text-field', value: 'Large' }] },
      ],
    })

    expect(payload(wrapper)).toEqual([
      ['tiers[0][fields][label]', 'Small'],
      ['tiers[1][fields][label]', 'Large'],
    ])
  })

  it('namespaces every sub-field of every row', () => {
    const wrapper = mountRows({ value: [{ fields: template() }] })

    expect(payload(wrapper).map(pair => pair[0])).toEqual([
      'tiers[0][fields][label]',
      'tiers[0][fields][price]',
    ])
  })

  it('uses the attribute it is given, which may differ from the key', () => {
    const wrapper = mountRows({ value: [{ fields: template() }] })

    expect(payload(wrapper, 'meta->tiers')[0][0]).toBe('meta->tiers[0][fields][label]')
  })
})

describe('RepeatableRows row identity', () => {
  it('gives each row its own field objects', () => {
    // Sharing one set across rows means every row renders and submits the
    // last row's data.
    const wrapper = mountRows({ value: [{ fields: template() }, { fields: template() }] })

    const [first, second] = wrapper.vm.rows

    expect(first.fields[0]).not.toBe(second.fields[0])

    first.fields[0].value = 'changed'

    expect(second.fields[0].value).toBe('')
  })

  it('keeps row keys stable across a reorder', () => {
    const wrapper = mountRows({ value: [{ fields: template() }, { fields: template() }] })

    const ids = wrapper.vm.rows.map(row => row.id)

    wrapper.vm.reorder({ from: 0, to: 1 })

    expect(wrapper.vm.rows.map(row => row.id)).toEqual([ids[1], ids[0]])
  })
})

describe('RepeatableRows min and max', () => {
  it('seeds the minimum number of rows', () => {
    expect(mountRows({ min: 2 }).vm.rows).toHaveLength(2)
  })

  it('stops adding at the maximum', () => {
    const wrapper = mountRows({ max: 1 })

    wrapper.vm.addRow()
    wrapper.vm.addRow()

    expect(wrapper.vm.rows).toHaveLength(1)
    expect(wrapper.vm.canAdd).toBe(false)
  })

  it('stops removing at the minimum', () => {
    const wrapper = mountRows({ min: 1 })

    wrapper.vm.removeRow(0)

    expect(wrapper.vm.rows).toHaveLength(1)
    expect(wrapper.vm.canRemove).toBe(false)
  })

  it('shows the budget rather than only disabling the button', () => {
    expect(mountRows({ max: 3, value: [{ fields: template() }] }).vm.counter)
      .toContain(':count of :max')
  })
})

describe('RepeatableRows reordering', () => {
  it('ignores a move past either end', () => {
    const wrapper = mountRows({ value: [{ fields: template() }] })
    const before = wrapper.vm.rows

    wrapper.vm.reorder({ from: 0, to: 1 })

    expect(wrapper.vm.rows).toBe(before)
  })

  it('announces a move for screen readers', () => {
    const wrapper = mountRows({ value: [{ fields: template() }, { fields: template() }] })

    wrapper.vm.reorder({ from: 0, to: 1 })

    expect(wrapper.vm.announcement).toContain('Moved to position')
  })
})
