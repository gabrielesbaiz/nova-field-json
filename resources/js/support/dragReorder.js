/**
 * Drag-to-reorder over native HTML5 drag events.
 *
 * Nova bundles no drag library (its own Repeater only offers up/down buttons),
 * and pulling one in would cost ~16KB gzip in a committed dist/ for something
 * that has to be re-implemented for the keyboard anyway. Native DnD gives us
 * the drag image for free and works across nested lists as long as each list
 * only accepts drops from its own scope.
 *
 * Keyboard users get Alt+ArrowUp / Alt+ArrowDown, which routes through the
 * same reorder reducer, so the two paths cannot drift apart.
 */

const MIME = 'application/x-njf-reorder'

/** The in-flight drag. Cleared on drop or dragend. */
let active = null

export function beginDrag(event, { scope, index }) {
  active = { scope, index }

  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    // Firefox refuses to start a drag unless some data is set.
    event.dataTransfer.setData(MIME, String(index))
    event.dataTransfer.setData('text/plain', String(index))
  }
}

export function endDrag() {
  active = null
}

/** Is the in-flight drag one this list should accept? */
export function isDragging(scope) {
  return active !== null && active.scope === scope
}

export function draggingIndex(scope) {
  return isDragging(scope) ? active.index : null
}

/**
 * Resolve a drop into a reorder, or null when it is not ours or is a no-op.
 *
 * @returns {{from: number, to: number}|null}
 */
export function resolveDrop(scope, targetIndex) {
  if (!isDragging(scope)) return null

  const from = active.index

  endDrag()

  if (from === targetIndex) return null

  return { from, to: targetIndex }
}

/** Handler for dragover: without preventDefault the drop never fires. */
export function allowDrop(event, scope) {
  if (!isDragging(scope)) return

  event.preventDefault()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}
