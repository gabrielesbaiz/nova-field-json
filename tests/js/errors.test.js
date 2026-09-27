import { describe, expect, it } from 'vitest'
import { Errors } from 'laravel-nova'
import { errorForPath, hasErrorUnder, rowFieldKey } from '../../resources/js/support/errors'

describe('errorForPath', () => {
  it('resolves a dotted path under the field key', () => {
    const errors = new Errors({ 'metadata.tiers.0.price': ['Required.'] })

    expect(errorForPath(errors, 'metadata', ['tiers', '0', 'price'])).toBe('Required.')
    expect(errorForPath(errors, 'metadata', ['tiers', '1', 'price'])).toBe(null)
  })

  it('is safe without errors or a key', () => {
    expect(errorForPath(null, 'metadata', ['a'])).toBe(null)
    expect(errorForPath(new Errors(), '', ['a'])).toBe(null)
  })
})

describe('hasErrorUnder', () => {
  it('prefix-matches the way Nova does', () => {
    const errors = new Errors({ 'metadata.a.b': ['Nope.'] })

    expect(hasErrorUnder(errors, 'metadata', ['a'])).toBe(true)
    expect(hasErrorUnder(errors, 'metadata', ['z'])).toBe(false)
  })
})

describe('rowFieldKey', () => {
  it('matches the key HandlesValidationErrors derives', () => {
    // nestedValidationKey() is `${viaParent}.${index}.fields.${attribute}`.
    // Posting `tiers[0][price]` instead would make Laravel report
    // `tiers.0.price` and every inline row error would silently vanish.
    expect(rowFieldKey('tiers', 0, 'price')).toBe('tiers.0.fields.price')
  })
})
