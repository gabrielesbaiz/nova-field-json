<script>
import DetailField from './DetailField'

// Nova's preview modal looks for `preview-{component}` and falls back to
// `detail-`; registering it explicitly keeps the peek modal tight.
export default {
  name: 'JsonEditorPreviewField',
  extends: DetailField,
}
</script>
