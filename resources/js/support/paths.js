/**
 * Immutable addressing into a decoded JSON structure.
 *
 * Mutations copy only the spine from the root to the changed node, leaving
 * every untouched subtree identical by reference. That keeps Vue's reactivity
 * cheap on deep documents and makes "did this branch change?" a `===` check.
 *
 * A path is an array of string keys; array indices are their decimal strings.
 * Paths are flattened to a single string with a NUL separator when they need
 * to be a Map key or a DOM attribute, since NUL cannot occur in a JSON key.
 */
import { isContainer, typeOf } from './json'

export const PATH_SEPARATOR = '\u0000'

export function pathKey(path) {
  return path.join(PATH_SEPARATOR)
}

export function keyToPath(key) {
  return key === '' ? [] : key.split(PATH_SEPARATOR)
}

export function parentOf(path) {
  return path.slice(0, -1)
}

export function lastOf(path) {
  return path[path.length - 1]
}

/** Shallow copy that preserves container kind and key order. */
function shallowCopy(node) {
  return Array.isArray(node) ? node.slice() : { ...node }
}

export function getAt(root, path) {
  let node = root

  for (const key of path) {
    if (!isContainer(node)) return undefined

    node = node[key]
  }

  return node
}

function containerFor(key) {
  return /^\d+$/.test(String(key)) ? [] : {}
}

/**
 * Return a copy of `root` with `path` set to `value`.
 *
 * Missing intermediate containers are created, choosing an array when the next
 * key looks like an index so `a.0.b` builds a list rather than a `{"0":...}`.
 */
export function setAt(root, path, value) {
  if (path.length === 0) return value

  const [key, ...rest] = path
  const copy = isContainer(root) ? shallowCopy(root) : containerFor(key)

  copy[key] = rest.length === 0 ? value : setAt(copy[key], rest, value)

  return copy
}

/** Return a copy of `root` with `path` removed; array elements close the gap. */
export function deleteAt(root, path) {
  if (path.length === 0) return root
  if (!isContainer(root)) return root

  const [key, ...rest] = path
  const copy = shallowCopy(root)

  if (rest.length > 0) {
    copy[key] = deleteAt(copy[key], rest)

    return copy
  }

  if (Array.isArray(copy)) {
    copy.splice(Number(key), 1)
  } else {
    delete copy[key]
  }

  return copy
}

/**
 * Rename an object key, keeping it in place.
 *
 * Rebuilding the object in iteration order matters: key order is visible in
 * the tree, and the obvious `delete` + assign would jump the key to the end on
 * every keystroke.
 */
export function renameKeyAt(root, path, newKey) {
  const parentPath = parentOf(path)
  const oldKey = lastOf(path)

  if (newKey === oldKey) return root

  const parent = getAt(root, parentPath)

  if (typeOf(parent) !== 'object') return root

  const renamed = {}

  for (const key of Object.keys(parent)) {
    if (key === oldKey) {
      renamed[newKey] = parent[key]
    } else if (key !== newKey) {
      renamed[key] = parent[key]
    }
  }

  return setAt(root, parentPath, renamed)
}

/** Move a child of the container at `parentPath` from one position to another. */
export function moveAt(root, parentPath, from, to) {
  const parent = getAt(root, parentPath)

  if (!isContainer(parent)) return root
  if (from === to) return root

  if (Array.isArray(parent)) {
    const copy = parent.slice()

    if (from < 0 || from >= copy.length || to < 0 || to >= copy.length) return root

    copy.splice(to, 0, copy.splice(from, 1)[0])

    return setAt(root, parentPath, copy)
  }

  const keys = Object.keys(parent)

  if (from < 0 || from >= keys.length || to < 0 || to >= keys.length) return root

  keys.splice(to, 0, keys.splice(from, 1)[0])

  const reordered = {}

  keys.forEach(key => {
    reordered[key] = parent[key]
  })

  return setAt(root, parentPath, reordered)
}

/**
 * The immediate children of a node, as renderable descriptors.
 *
 * @returns {Array<{key: string, value: *, path: string[], index: number}>}
 */
export function childPaths(node, path = []) {
  if (!isContainer(node)) return []

  const keys = Array.isArray(node) ? node.map((_, i) => String(i)) : Object.keys(node)

  return keys.map((key, index) => ({
    key,
    index,
    value: node[key],
    path: [...path, key],
  }))
}

/** A key not already present in the object, e.g. `key`, `key_2`, `key_3`. */
export function uniqueKey(object, base = 'key') {
  if (!(base in object)) return base

  let n = 2

  while (`${base}_${n}` in object) n += 1

  return `${base}_${n}`
}
