/**
 * Stable row identity without pulling in a dependency.
 *
 * Rows need a key that survives reordering, and the value itself cannot serve
 * as one because two rows may be identical.
 */
let counter = 0

export function uid(prefix = 'njf') {
  counter += 1

  return `${prefix}-${counter.toString(36)}`
}
