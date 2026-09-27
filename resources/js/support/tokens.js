/**
 * One hue per JSON type, used identically by the tree, the key/value rows and
 * the read-only viewer so a type is recognisable wherever it appears.
 *
 * These compile to `rgba(var(--colors-*))` under Nova's Tailwind preset, so
 * they follow a user's custom Nova theme instead of baking in literal hex.
 */
export const TYPE_CLASSES = {
  string: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
  number: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400',
  boolean: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-400',
  null: 'bg-gray-100 text-gray-500 dark:bg-gray-500/10 dark:text-gray-400',
  object: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
  array: 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
}

export function typeClasses(type) {
  return TYPE_CLASSES[type] || TYPE_CLASSES.string
}
