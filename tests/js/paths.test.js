import { describe, expect, it } from 'vitest'
import {
  childPaths, deleteAt, getAt, keyToPath, moveAt, pathKey,
  renameKeyAt, setAt, uniqueKey,
} from '../../resources/js/support/paths'

describe('getAt', () => {
  it('walks a path and gives up safely on a scalar', () => {
    expect(getAt({ a: { b: 1 } }, ['a', 'b'])).toBe(1)
    expect(getAt({ a: 1 }, ['a', 'b'])).toBeUndefined()
    expect(getAt({ a: [10, 20] }, ['a', '1'])).toBe(20)
  })
})

describe('setAt', () => {
  it('returns a new root without mutating the old one', () => {
    const before = { a: { b: 1 }, c: 2 }
    const after = setAt(before, ['a', 'b'], 9)

    expect(before.a.b).toBe(1)
    expect(after.a.b).toBe(9)
  })

  it('shares every untouched subtree by reference', () => {
    const before = { a: { b: 1 }, untouched: { deep: [1, 2, 3] } }
    const after = setAt(before, ['a', 'b'], 9)

    expect(after.untouched).toBe(before.untouched)
    expect(after.a).not.toBe(before.a)
  })

  it('preserves key order when overwriting an existing key', () => {
    const after = setAt({ a: 1, b: 2, c: 3 }, ['b'], 9)

    expect(Object.keys(after)).toEqual(['a', 'b', 'c'])
  })

  it('creates an array when the next key looks like an index', () => {
    expect(setAt({}, ['list', '0'], 'x').list).toEqual(['x'])
    expect(Array.isArray(setAt({}, ['map', 'k'], 'x').map)).toBe(false)
  })

  it('replaces the root for an empty path', () => {
    expect(setAt({ a: 1 }, [], [1, 2])).toEqual([1, 2])
  })
})

describe('deleteAt', () => {
  it('removes an object key and closes an array gap', () => {
    expect(deleteAt({ a: 1, b: 2 }, ['b'])).toEqual({ a: 1 })
    expect(deleteAt({ list: [1, 2, 3] }, ['list', '1']).list).toEqual([1, 3])
  })

  it('does not mutate the original', () => {
    const before = { a: 1, b: 2 }
    deleteAt(before, ['b'])

    expect(before).toEqual({ a: 1, b: 2 })
  })
})

describe('renameKeyAt', () => {
  it('keeps the renamed key in position', () => {
    const after = renameKeyAt({ a: 1, b: 2, c: 3 }, ['b'], 'beta')

    expect(Object.keys(after)).toEqual(['a', 'beta', 'c'])
    expect(after.beta).toBe(2)
  })

  it('renames a nested key', () => {
    const after = renameKeyAt({ outer: { a: 1, b: 2 } }, ['outer', 'a'], 'alpha')

    expect(Object.keys(after.outer)).toEqual(['alpha', 'b'])
  })

  it('is a no-op when the name is unchanged', () => {
    const before = { a: 1 }

    expect(renameKeyAt(before, ['a'], 'a')).toBe(before)
  })

  it('drops a duplicate rather than emitting the key twice', () => {
    const after = renameKeyAt({ a: 1, b: 2 }, ['a'], 'b')

    expect(Object.keys(after)).toEqual(['b'])
    expect(after.b).toBe(1)
  })
})

describe('moveAt', () => {
  it('reorders array elements', () => {
    expect(moveAt({ list: [1, 2, 3] }, ['list'], 0, 2).list).toEqual([2, 3, 1])
  })

  it('reorders object keys', () => {
    const after = moveAt({ a: 1, b: 2, c: 3 }, [], 2, 0)

    expect(Object.keys(after)).toEqual(['c', 'a', 'b'])
  })

  it('ignores an out-of-range or no-op move', () => {
    const before = { list: [1, 2] }

    expect(moveAt(before, ['list'], 0, 0)).toBe(before)
    expect(moveAt(before, ['list'], 0, 5)).toBe(before)
    expect(moveAt(before, ['missing'], 0, 1)).toBe(before)
  })
})

describe('childPaths', () => {
  it('describes object and array children uniformly', () => {
    expect(childPaths({ a: 1 }, ['root'])).toEqual([
      { key: 'a', index: 0, value: 1, path: ['root', 'a'] },
    ])
    expect(childPaths(['x'], []).map(c => c.key)).toEqual(['0'])
    expect(childPaths('scalar', [])).toEqual([])
  })
})

describe('pathKey', () => {
  it('round-trips through a flat string key', () => {
    const path = ['a', 'b.c', 'd']

    expect(keyToPath(pathKey(path))).toEqual(path)
    expect(keyToPath('')).toEqual([])
  })
})

describe('uniqueKey', () => {
  it('suffixes until the key is free', () => {
    expect(uniqueKey({}, 'key')).toBe('key')
    expect(uniqueKey({ key: 1 }, 'key')).toBe('key_2')
    expect(uniqueKey({ key: 1, key_2: 1 }, 'key')).toBe('key_3')
  })
})
