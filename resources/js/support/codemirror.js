/**
 * CodeMirror 5, matching the version Nova's own Code field uses.
 *
 * Nova compiles CodeMirror into its vendor bundle but never exposes it on
 * `window`, and it is not one of nova-devtool's webpack externals, so the JS
 * has to be bundled here. The base CSS *is* already in Nova's app.css, but we
 * ship a scoped copy anyway: relying on Nova continuing to import a v5
 * stylesheet is the single most likely thing to break on a Nova upgrade.
 *
 * `addon/lint` is deliberately absent -- its CSS is not shipped by Nova, and a
 * hand-rolled error banner reads better than the lint tooltip anyway.
 */
import CodeMirror from 'codemirror'
import 'codemirror/mode/javascript/javascript'
import 'codemirror/addon/edit/matchbrackets'
import 'codemirror/addon/edit/closebrackets'
import 'codemirror/addon/selection/active-line'
import 'codemirror/addon/display/autorefresh'

export default CodeMirror

export const DARK_THEME = 'dracula'
export const LIGHT_THEME = 'default'

export function preferredTheme() {
  return typeof document !== 'undefined'
    && document.documentElement.classList.contains('dark')
    ? DARK_THEME
    : LIGHT_THEME
}

/**
 * Keep the editor theme in step with Nova's theme toggle.
 *
 * Nova's own CodeField reads the theme once and never updates, so toggling
 * dark mode leaves the editor light until a page reload.
 *
 * @returns {function(): void} disposer
 */
export function watchTheme(onChange) {
  if (typeof MutationObserver === 'undefined') return () => {}

  const observer = new MutationObserver(() => onChange(preferredTheme()))

  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
  })

  return () => observer.disconnect()
}
