import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { Errors } from 'laravel-nova'
import FormField from '../../resources/js/components/FormField.vue'

function makeField(overrides = {}) {
  return {
    attribute: 'metadata',
    validationKey: 'metadata',
    name: 'Metadata',
    component: 'json-editor-field',
    mode: 'tree',
    value: { a: 1 },
    ...overrides,
  }
}

function mountField(field = makeField(), errors = new Errors()) {
  return mount(FormField, {
    props: { field, errors, resourceName: 'products', resourceId: 1 },
  })
}

/** The pairs a real submit would send. */
function payload(wrapper) {
  const formData = new FormData()

  wrapper.vm.fill(formData)

  return [...formData.entries()]
}

describe('FormField value handling', () => {
  it('accepts an already decoded value from a cast column', () => {
    expect(mountField().vm.value).toEqual({ a: 1 })
  })

  it('accepts a JSON string from an uncast column', () => {
    expect(mountField(makeField({ value: '{"a":1}' })).vm.value).toEqual({ a: 1 })
  })

  it('falls back to an empty object rather than rendering nothing', () => {
    expect(mountField(makeField({ value: null })).vm.value).toEqual({})
  })
})

describe('FormField fill', () => {
  it('posts one key holding a JSON string', () => {
    expect(payload(mountField())).toEqual([['metadata', '{"a":1}']])
  })

  it('posts the literal text when the raw editor is left unparseable', () => {
    // Silently dropping the user's typing is worse than letting the server's
    // `json` rule reject it, which is what this makes happen.
    const wrapper = mountField()
    wrapper.vm.rawDraft = '{"a":1,'

    expect(payload(wrapper)).toEqual([['metadata', '{"a":1,']])
  })

  it('posts the edited value after an update', () => {
    const wrapper = mountField()

    wrapper.vm.onInput({ a: 2, b: 'x' })

    expect(payload(wrapper)).toEqual([['metadata', '{"a":2,"b":"x"}']])
  })

  it('uses the field attribute, which may differ from the name', () => {
    const wrapper = mountField(makeField({ attribute: 'meta->settings' }))

    expect(payload(wrapper)[0][0]).toBe('meta->settings')
  })
})

describe('FormField mode selection', () => {
  it('starts in the mode the PHP field declared', () => {
    expect(mountField(makeField({ mode: 'keyvalue' })).vm.activeMode).toBe('keyvalue')
    expect(mountField(makeField({ mode: 'raw' })).vm.activeMode).toBe('raw')
  })

  it('falls back to raw for a document too large to render as a tree', () => {
    const huge = {}

    for (let i = 0; i < 5100; i += 1) huge[`k${i}`] = i

    const wrapper = mountField(makeField({ value: huge }))

    expect(wrapper.vm.activeMode).toBe('raw')
    expect(wrapper.vm.forcedToRaw).toBe(true)
    expect(wrapper.text()).toContain('too large')
  })

  it('offers no mode switcher for a repeatable field', () => {
    const wrapper = mountField(makeField({ mode: 'repeatable', value: [], rowTemplate: [] }))

    expect(wrapper.find('[role="radiogroup"]').exists()).toBe(false)
  })
})

describe('FormField validation wiring', () => {
  it('reads errors under the validation key, not the attribute', () => {
    const field = makeField({ attribute: 'pivot_metadata', validationKey: 'metadata' })
    const wrapper = mountField(field, new Errors({ metadata: ['Bad JSON.'] }))

    expect(wrapper.vm.validationKey).toBe('metadata')
    expect(wrapper.vm.hasError).toBe(true)
  })

  it('surfaces a nested error through Nova prefix matching', () => {
    const wrapper = mountField(makeField(), new Errors({ 'metadata.a': ['Nope.'] }))

    expect(wrapper.vm.hasError).toBe(true)
  })
})
