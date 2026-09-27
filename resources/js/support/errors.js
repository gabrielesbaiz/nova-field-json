/**
 * Map an editor path onto the validation key Laravel reported it under.
 *
 * Nova's Errors class already prefix-matches for the field as a whole, so
 * DefaultField shows the first error with no help from us. This is only for
 * putting a message on the individual input that caused it.
 */

/** Always use field.validationKey: it differs from `attribute` in pivot forms. */
export function errorForPath(errors, validationKey, path) {
  if (!errors || !validationKey) return null

  const key = [validationKey, ...path].join('.')

  return (errors.first && errors.first(key)) || null
}

export function hasErrorUnder(errors, validationKey, path) {
  if (!errors || !validationKey) return false

  const key = [validationKey, ...path].join('.')

  return Boolean(errors.has && errors.has(key))
}

/**
 * The key Nova's nested-field convention produces for a repeatable sub-field.
 *
 * HandlesValidationErrors derives this as `{viaParent}.{index}.fields.{attribute}`,
 * which is why the fill payload must use the `[fields]` segment.
 */
export function rowFieldKey(validationKey, index, attribute) {
  return `${validationKey}.${index}.fields.${attribute}`
}
