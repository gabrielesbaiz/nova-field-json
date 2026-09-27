// NOTE: do not copy vendor/laravel/nova-devtool/src/Console/stubs/tailwind.config.js.
// It uses `preset` (the key is `presets`) and `purge` (Tailwind v2 syntax, dead
// in the v3 both Nova and devtool pin), so it silently drops the Nova theme and
// emits nothing at all.
const novaPreset = require('./vendor/laravel/nova/tailwind.config.js')

module.exports = {
  // The preset is what maps `bg-gray-800` to `rgba(var(--colors-gray-800))`.
  // Without it we bake literal hex and stop following a user's Nova theme.
  presets: [novaPreset],

  // Nova toggles `html.dark` itself; media-query dark mode would ignore it.
  darkMode: 'class',

  content: [
    './resources/js/**/*.{js,vue}',
    './resources/css/**/*.css',
    './src/**/*.php',
  ],

  // Nova already ships preflight; re-emitting it would reset the whole panel.
  corePlugins: { preflight: false },

  theme: { extend: {} },

  // Does not remove the preset's plugins -- Tailwind merges them, which is why
  // the `unique` postcss plugin in webpack.mix.js is needed.
  plugins: [],
}
