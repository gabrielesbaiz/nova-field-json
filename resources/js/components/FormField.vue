<template>
  <DefaultField
    :field="currentField"
    :errors="errors"
    :show-help-text="showHelpText"
    :full-width-content="true"
  >
    <template #field>
      <div class="space-y-3" :dusk="`${fieldAttribute}-json-editor`">
        <div class="flex flex-wrap items-center gap-2">
          <ModeSwitcher
            v-if="!isRepeatable && availableModes.length > 1"
            v-model="activeMode"
            :modes="availableModes"
            :disabled="currentlyIsReadonly"
          />

          <div class="ml-auto flex items-center gap-1">
            <Button
              v-if="activeMode === 'raw' && !isRepeatable"
              type="button"
              variant="ghost"
              size="small"
              :disabled="currentlyIsReadonly"
              @click="$refs.raw?.format()"
            >
              {{ __('Format') }}
            </Button>

            <Button
              v-if="!isRepeatable"
              type="button"
              variant="ghost"
              size="small"
              padding="tight"
              icon="clipboard"
              :aria-label="__('Copy JSON to clipboard')"
              @click="copy"
            />
          </div>
        </div>

        <p
          v-if="forcedToRaw"
          class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400"
        >
          {{ __('This value is too large to edit as a tree; showing the raw source instead.') }}
        </p>

        <RepeatableRows
          v-if="isRepeatable"
          ref="repeatable"
          :field="currentField"
          :validation-key="validationKey"
          :resource-name="resourceName"
          :resource-id="resourceId"
          :readonly="currentlyIsReadonly"
          :errors="errors"
        />

        <template v-else>
          <RawEditor
            v-if="activeMode === 'raw'"
            ref="raw"
            :model-value="value"
            :readonly="currentlyIsReadonly"
            :height="currentField.height || 320"
            @update:model-value="onInput"
            @parse-error="error => (parseError = error)"
            @raw-draft="draft => (rawDraft = draft)"
          />

          <KeyValueEditor
            v-else-if="activeMode === 'keyvalue'"
            :model-value="value"
            :readonly="currentlyIsReadonly"
            :allow-type-change="currentField.allowTypeChange !== false"
            :errors="errors"
            :validation-key="validationKey"
            @update:model-value="onInput"
          />

          <TreeEditor
            v-else
            :model-value="value"
            :readonly="currentlyIsReadonly"
            :allow-type-change="currentField.allowTypeChange !== false"
            :locked-keys="currentField.lockedKeys || []"
            :errors="errors"
            :validation-key="validationKey"
            :expand-depth="currentField.expandDepth ?? 1"
            :label="currentField.name"
            @update:model-value="onInput"
          />

          <ParseErrorBanner
            v-if="parseError"
            :error="parseError"
            :can-locate="activeMode === 'raw'"
            @locate="error => $refs.raw?.locate(error)"
          />
        </template>
      </div>
    </template>
  </DefaultField>
</template>

<script>
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova'
import { Button } from 'laravel-nova-ui'
import ModeSwitcher from './ModeSwitcher'
import RawEditor from './editors/RawEditor'
import KeyValueEditor from './editors/KeyValueEditor'
import TreeEditor from './editors/TreeEditor'
import RepeatableRows from './repeatable/RepeatableRows'
import ParseErrorBanner from './ui/ParseErrorBanner'
import { countNodes, decode, stringify } from '../support/json'

/** Above this many nodes a role="tree" becomes unusably slow to render. */
const TREE_NODE_LIMIT = 5000

export default {
  name: 'JsonEditorFormField',

  mixins: [HandlesValidationErrors, DependentFormField],

  components: {
    Button,
    KeyValueEditor,
    ModeSwitcher,
    ParseErrorBanner,
    RawEditor,
    RepeatableRows,
    TreeEditor,
  },

  data: () => ({
    activeMode: 'tree',
    parseError: null,
    rawDraft: null,
    forcedToRaw: false,
  }),

  created() {
    this.activeMode = this.currentField.mode === 'repeatable'
      ? 'tree'
      : this.currentField.mode || 'tree'

    if (countNodes(this.value, TREE_NODE_LIMIT + 1) > TREE_NODE_LIMIT) {
      this.activeMode = 'raw'
      this.forcedToRaw = true
    }
  },

  methods: {
    /**
     * Nova hands us either a JSON string (uncast column) or an already decoded
     * structure (array/json cast); both arrive here as one value.
     */
    setInitialValue() {
      this.value = decode(this.currentField.value, this.currentField.mode === 'repeatable' ? [] : {})
    },

    fieldDefaultValue() {
      return {}
    },

    onInput(next) {
      this.value = next
      this.parseError = null
      this.rawDraft = null

      this.emitFieldValueChange(this.fieldAttribute, this.value)
    },

    fill(formData) {
      if (this.isRepeatable) {
        if (this.currentlyIsVisible) {
          this.$refs.repeatable?.fillRows(formData, this.fieldAttribute)
        }

        return
      }

      // If the raw editor was left unparseable, submit the literal text so the
      // server reports a real validation error rather than us quietly
      // discarding whatever the user typed.
      const payload = this.rawDraft !== null ? this.rawDraft : stringify(this.value)

      this.fillIfVisible(formData, this.fieldAttribute, payload)
    },

    async copy() {
      try {
        await navigator.clipboard.writeText(stringify(this.value, 2))
        Nova.success(this.__('Copied to clipboard.'))
      } catch (e) {
        Nova.error(this.__('Could not copy to clipboard.'))
      }
    },

    onSyncedField() {
      this.setInitialValue()
      this.parseError = null
      this.rawDraft = null
    },
  },

  computed: {
    availableModes() {
      return this.forcedToRaw ? ['raw'] : ['tree', 'keyvalue', 'raw']
    },

    isRepeatable() {
      return this.currentField.mode === 'repeatable'
    },
  },
}
</script>
