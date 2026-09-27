import { describe, expect, it } from 'vitest'
import {
  blankFor, coerce, countNodes, decode, format, isEmptyValue,
  offsetToLineCol, parseError, stringify, summarize, typeOf,
} from '../../resources/js/support/json'

describe('typeOf', () => {
  it('distinguishes the six JSON types', () => {
    expect(typeOf('a')).toBe('string')
    expect(typeOf(1)).toBe('number')
    expect(typeOf(true)).toBe('boolean')
    expect(typeOf(null)).toBe('null')
    expect(typeOf(undefined)).toBe('null')
    expect(typeOf([])).toBe('array')
    expect(typeOf({})).toBe('object')
  })
})

describe('decode', () => {
  it('accepts a JSON string', () => {
    expect(decode('{"a":1}')).toEqual({ a: 1 })
  })

  it('passes an already decoded structure through untouched', () => {
    const value = { a: 1 }

    expect(decode(value)).toBe(value)
  })

  it('falls back for null, empty and unparseable input', () => {
    expect(decode(null, {})).toEqual({})
    expect(decode('', {})).toEqual({})
    expect(decode('{not json', { fallback: true })).toEqual({ fallback: true })
    expect(decode('null', { fallback: true })).toEqual({ fallback: true })
  })
})

describe('format', () => {
  it('re-indents valid JSON', () => {
    expect(format('{"a":1}')).toBe('{\n  "a": 1\n}')
  })

  it('leaves invalid JSON untouched rather than destroying the user\'s text', () => {
    expect(format('{"a":1,')).toBe('{"a":1,')
  })

  it('is stable when applied twice', () => {
    const once = format('{"a":[1,2],"b":{"c":3}}')

    expect(format(once)).toBe(once)
  })
})

describe('coerce', () => {
  it('converts to the requested type', () => {
    expect(coerce('42', 'number')).toBe(42)
    expect(coerce('nope', 'number')).toBe(0)
    expect(coerce('true', 'boolean')).toBe(true)
    expect(coerce('0', 'boolean')).toBe(false)
    expect(coerce('anything', 'null')).toBe(null)
    expect(coerce(42, 'string')).toBe('42')
  })
})

describe('blankFor', () => {
  it('keeps a compatible previous value', () => {
    const previous = { a: 1 }

    expect(blankFor('object', previous)).toBe(previous)
    expect(blankFor('string', 42)).toBe('42')
    expect(blankFor('string', { a: 1 })).toBe('')
    expect(blankFor('array', { a: 1 })).toEqual([])
  })
})

describe('summarize', () => {
  it('labels containers by size', () => {
    expect(summarize({ a: 1, b: 2 }).label).toBe('{2}')
    expect(summarize([1, 2, 3]).label).toBe('[3]')
  })
})

describe('isEmptyValue', () => {
  it('treats blank containers and blank scalars as empty', () => {
    expect(isEmptyValue(null)).toBe(true)
    expect(isEmptyValue('')).toBe(true)
    expect(isEmptyValue([])).toBe(true)
    expect(isEmptyValue({})).toBe(true)
    expect(isEmptyValue(0)).toBe(false)
    expect(isEmptyValue(false)).toBe(false)
  })
})

describe('countNodes', () => {
  it('counts every node, stopping at the limit', () => {
    expect(countNodes({ a: { b: 1 }, c: [1, 2] })).toBe(6)
    expect(countNodes({ a: { b: 1 }, c: [1, 2] }, 3)).toBeLessThanOrEqual(4)
  })
})

describe('offsetToLineCol', () => {
  it('is 1-based on both axes', () => {
    expect(offsetToLineCol('abc', 0)).toEqual({ line: 1, column: 1 })
    expect(offsetToLineCol('ab\ncd', 4)).toEqual({ line: 2, column: 2 })
  })

  it('clamps an out-of-range offset', () => {
    expect(offsetToLineCol('ab', 99)).toEqual({ line: 1, column: 3 })
  })
})

describe('parseError', () => {
  it('returns null for valid JSON', () => {
    expect(parseError('{"a":1}')).toBe(null)
  })

  it('locates the failure', () => {
    const error = parseError('{\n  "a": 1,\n}')

    expect(error).not.toBe(null)
    expect(error.line).toBeGreaterThanOrEqual(1)
    expect(error.column).toBeGreaterThanOrEqual(1)
    expect(typeof error.message).toBe('string')
  })
})

describe('stringify', () => {
  it('survives a value it cannot serialise', () => {
    const circular = {}
    circular.self = circular

    expect(stringify(circular)).toBe('""')
  })
})
