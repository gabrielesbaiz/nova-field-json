import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// `laravel-nova` and `laravel-nova-ui` are webpack externals resolved from
// Nova's runtime bundle, so they do not exist on disk. Point them at stubs.
const stub = name => fileURLToPath(new URL(`./tests/js/stubs/${name}.js`, import.meta.url))

export default defineConfig({
  plugins: [vue()],
  resolve: {
    // Nova's own field code imports components without an extension, which
    // webpack resolves but Vite does not by default.
    extensions: ['.mjs', '.js', '.json', '.vue'],
    alias: {
      'laravel-nova': stub('laravel-nova'),
      'laravel-nova-ui': stub('laravel-nova-ui'),
    },
  },
  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['./tests/js/setup.js'],
    include: ['tests/js/**/*.test.js'],
  },
})
