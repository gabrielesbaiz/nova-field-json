import IndexField from './components/IndexField'
import DetailField from './components/DetailField'
import FormField from './components/FormField'
import PreviewField from './components/PreviewField'

// Static imports only: a dynamic import() emits a webpack chunk that never
// appears in mix-manifest.json, so Nova would never load it.
Nova.booting(app => {
  app.component('index-json-editor-field', IndexField)
  app.component('detail-json-editor-field', DetailField)
  app.component('form-json-editor-field', FormField)
  app.component('preview-json-editor-field', PreviewField)
})
