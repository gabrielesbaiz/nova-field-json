/**
 * Minimal stand-ins for the `laravel-nova` external.
 *
 * Errors is copied from Nova's own util/FormValidation so the prefix-matching
 * behaviour under test is the real one, not an approximation of it.
 */
export class Errors {
  constructor(errors = {}) {
    this.errors = errors
  }

  has(field) {
    let hasError = Object.prototype.hasOwnProperty.call(this.errors, field)

    if (!hasError) {
      const errors = Object.keys(this.errors).filter(
        e => e.startsWith(`${field}.`) || e.startsWith(`${field}[`)
      )

      hasError = errors.length > 0
    }

    return hasError
  }

  first(field) {
    return this.get(field)[0]
  }

  get(field) {
    return this.errors[field] || []
  }

  any() {
    return Object.keys(this.errors).length > 0
  }
}

export const FormField = {
  props: ['resourceName', 'resourceId', 'field'],

  data: () => ({ value: '', showHelpText: false }),

  created() {
    this.setInitialValue()
  },

  mounted() {
    this.field.fill = this.fill
  },

  methods: {
    setInitialValue() {
      this.value = this.field.value ?? ''
    },
    fill(formData) {
      this.fillIfVisible(formData, this.fieldAttribute, this.value)
    },
    fillIfVisible(formData, attribute, value) {
      formData.append(attribute, value)
    },
    emitFieldValueChange() {},
  },

  computed: {
    fieldAttribute() {
      return this.field.attribute
    },
    currentField() {
      return this.field
    },
    currentlyIsReadonly() {
      return Boolean(this.field.readonly)
    },
    currentlyIsVisible() {
      return true
    },
  },
}

export const DependentFormField = {
  mixins: [FormField],
  methods: { onSyncedField() {} },
}

export const HandlesValidationErrors = {
  props: { errors: { default: () => new Errors() } },

  computed: {
    errorClasses() {
      return this.hasError ? ['form-control-bordered-error'] : []
    },
    validationKey() {
      return this.field.validationKey || this.field.attribute
    },
    hasError() {
      return this.errors.has(this.validationKey)
    },
    firstError() {
      return this.errors.first(this.validationKey)
    },
  },
}

export const FieldValue = {}

export const mapProps = keys =>
  Object.fromEntries(keys.map(key => [key, { type: [String, Number, Boolean, Object], default: null }]))
