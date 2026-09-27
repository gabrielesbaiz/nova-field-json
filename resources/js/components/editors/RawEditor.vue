<template>
  <div
    class="njf-editor overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
    :class="{ 'ring-1 ring-red-400 dark:ring-red-500': Boolean(parsed) }"
  >
    <textarea ref="textarea" :value="text" class="hidden" />
  </div>
</template>

<script>
import CodeMirror, { preferredTheme, watchTheme } from '../../support/codemirror'
import { format, parseError, stringify } from '../../support/json'

export default {
  name: 'RawEditor',

  props: {
    modelValue: { default: () => ({}) },
    readonly: { type: Boolean, default: false },
    height: { type: Number, default: 320 },
    theme: { type: String, default: null },
  },

  emits: ['update:modelValue', 'parse-error', 'raw-draft'],

  data() {
    return { text: stringify(this.modelValue, 2), parsed: null }
  },

  mounted() {
    this.editor = CodeMirror.fromTextArea(this.$refs.textarea, {
      mode: { name: 'javascript', json: true },
      theme: this.theme || preferredTheme(),
      lineNumbers: true,
      lineWrapping: true,
      matchBrackets: true,
      autoCloseBrackets: true,
      styleActiveLine: true,
      autoRefresh: true,
      readOnly: this.readonly ? 'nocursor' : false,
      tabSize: 2,
      // Registered on the editor, not the document: Nova installs a Mousetrap
      // stopCallback override that global handlers end up fighting.
      extraKeys: {
        'Cmd-Shift-F': () => this.format(),
        'Ctrl-Shift-F': () => this.format(),
      },
    })

    this.editor.setSize(null, this.height)
    this.editor.on('change', this.onChange)

    this.disposeTheme = watchTheme(theme => {
      if (!this.theme) this.editor.setOption('theme', theme)
    })
  },

  beforeUnmount() {
    this.disposeTheme?.()
    this.editor?.toTextArea()
  },

  watch: {
    modelValue(next) {
      const incoming = stringify(next, 2)

      // Do not fight the user's cursor while they are mid-edit.
      if (this.editor && incoming !== this.editor.getValue() && this.parsed === null) {
        this.editor.setValue(incoming)
      }
    },

    readonly(next) {
      this.editor?.setOption('readOnly', next ? 'nocursor' : false)
    },
  },

  methods: {
    onChange(editor) {
      const text = editor.getValue()

      this.clearErrorLine()

      const error = parseError(text)

      this.parsed = error

      if (error) {
        this.markErrorLine(error.line - 1)
        this.$emit('raw-draft', text)
        this.$emit('parse-error', error)

        return
      }

      this.$emit('raw-draft', null)
      this.$emit('parse-error', null)
      this.$emit('update:modelValue', JSON.parse(text))
    },

    format() {
      if (!this.editor || this.readonly) return

      const cursor = this.editor.getCursor()

      this.editor.setValue(format(this.editor.getValue(), 2))
      this.editor.setCursor(cursor)
    },

    locate(error) {
      this.editor?.focus()
      this.editor?.setCursor({ line: error.line - 1, ch: Math.max(0, error.column - 1) })
    },

    markErrorLine(line) {
      if (!this.editor || line < 0 || line >= this.editor.lineCount()) return

      this.errorLine = line
      this.editor.addLineClass(line, 'background', 'njf-cm-error-line')
    },

    clearErrorLine() {
      if (this.errorLine === undefined || this.errorLine === null || !this.editor) return

      this.editor.removeLineClass(this.errorLine, 'background', 'njf-cm-error-line')
      this.errorLine = null
    },
  },
}
</script>
