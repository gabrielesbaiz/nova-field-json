/**
 * JSON value helpers.
 *
 * Everything here is pure and side-effect free so the editors can be reasoned
 * about (and unit tested) without mounting a component.
 */

export const TYPES = ['string', 'number', 'boolean', 'null', 'array', 'object']

/**
 * The JSON type of a decoded value.
 *
 * Distinguishes array from object, which `typeof` does not, and treats null as
 * its own type rather than as an object.
 */
export function typeOf(value) {
  if (value === null || value === undefined) return 'null'
  if (Array.isArray(value)) return 'array'

  switch (typeof value) {
    case 'boolean':
      return 'boolean'
    case 'number':
      return 'number'
    case 'object':
      return 'object'
    default:
      return 'string'
  }
}

export function isContainer(value) {
  const type = typeOf(value)

  return type === 'object' || type === 'array'
}

/**
 * Nova hands us either a JSON string (uncast column) or an already decoded
 * structure (array/json cast). Normalise, falling back when unusable.
 */
export function decode(value, fallback = {}) {
  if (value === null || value === undefined || value === '') return fallback
  if (typeof value === 'object') return value

  try {
    const parsed = JSON.parse(value)

    return parsed === null ? fallback : parsed
  } catch (e) {
    return fallback
  }
}

export function stringify(value, indent = 0) {
  try {
    return JSON.stringify(value === undefined ? null : value, null, indent)
  } catch (e) {
    // Circular structures cannot occur in decoded JSON, but a Vue proxy over a
    // user-supplied object might; never let serialisation take down the form.
    return '""'
  }
}

/** Re-indent a JSON source string, leaving it untouched if it will not parse. */
export function format(text, indent = 2) {
  try {
    return JSON.stringify(JSON.parse(text), null, indent)
  } catch (e) {
    return text
  }
}

/** A blank value of the given type, keeping what it can of the previous one. */
export function blankFor(type, previous = undefined) {
  switch (type) {
    case 'object':
      return typeOf(previous) === 'object' ? previous : {}
    case 'array':
      return typeOf(previous) === 'array' ? previous : []
    case 'number': {
      const n = Number(previous)

      return Number.isFinite(n) ? n : 0
    }
    case 'boolean':
      return Boolean(previous)
    case 'null':
      return null
    default:
      return previous === null || previous === undefined || isContainer(previous)
        ? ''
        : String(previous)
  }
}

/** Convert a raw input value into the given JSON type. */
export function coerce(value, type) {
  switch (type) {
    case 'number': {
      const n = Number(value)

      return Number.isFinite(n) ? n : 0
    }
    case 'boolean':
      return value === true || value === 'true' || value === 1 || value === '1'
    case 'null':
      return null
    case 'array':
      return Array.isArray(value) ? value : []
    case 'object':
      return typeOf(value) === 'object' ? value : {}
    default:
      return value === null || value === undefined ? '' : String(value)
  }
}

export function isEmptyValue(value) {
  if (value === null || value === undefined || value === '') return true
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'object') return Object.keys(value).length === 0

  return false
}

/** A short human label for a container, e.g. `{3 keys}` / `[5 items]`. */
export function summarize(value) {
  const type = typeOf(value)

  if (type === 'array') {
    return { type, count: value.length, label: `[${value.length}]` }
  }

  if (type === 'object') {
    const count = Object.keys(value).length

    return { type, count, label: `{${count}}` }
  }

  return { type, count: 0, label: type }
}

/** Total node count, used to fall back to raw mode on very large documents. */
export function countNodes(value, limit = Infinity) {
  let total = 0

  const walk = node => {
    if (total >= limit) return

    total += 1

    if (Array.isArray(node)) {
      node.forEach(walk)
    } else if (node !== null && typeof node === 'object') {
      Object.values(node).forEach(walk)
    }
  }

  walk(value)

  return total
}

/** Translate a character offset into a 1-based line and column. */
export function offsetToLineCol(text, offset) {
  const clamped = Math.max(0, Math.min(offset, text.length))
  const before = text.slice(0, clamped)
  const lines = before.split('\n')

  return { line: lines.length, column: lines[lines.length - 1].length + 1 }
}

/**
 * Parse a JSON source string, returning a located error rather than throwing.
 *
 * Engines report the failure position differently ("at position 12",
 * "line 3 column 5"), so read whichever is present.
 *
 * @returns {{message: string, line: number, column: number}|null}
 */
export function parseError(text) {
  try {
    JSON.parse(text)

    return null
  } catch (e) {
    const message = e.message || 'Invalid JSON'

    const lineCol = /line (\d+) column (\d+)/i.exec(message)

    if (lineCol) {
      return { message, line: Number(lineCol[1]), column: Number(lineCol[2]) }
    }

    const position = /position (\d+)/i.exec(message)

    if (position) {
      return { message, ...offsetToLineCol(text, Number(position[1])) }
    }

    return { message, line: 1, column: 1 }
  }
}
