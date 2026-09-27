const mix = require('laravel-mix')
const NovaExtension = require('laravel-nova-devtool')

mix.extend('nova', new NovaExtension())

mix
  .setPublicPath('dist')
  .js('resources/js/field.js', 'js')
  .vue({ version: 3 })
  .css('resources/css/field.css', 'css', [
    require('postcss-import'),
    require('tailwindcss'),
    require('autoprefixer'),
    // Nova's Tailwind preset re-emits its whole `:root { --colors-* }` block.
    // Strip any rule whose selector Nova's compiled app.css already carries.
    require('laravel-nova-devtool/unique')({
      path: 'vendor/laravel/nova/public/app.css',
    }),
  ])
  .nova('gabrielesbaiz/nova-field-json')
  .version()

mix.options({ terser: { extractComments: false } })
