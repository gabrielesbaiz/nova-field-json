import { config } from '@vue/test-utils'

// Nova installs a global `__()` mixin and registers a handful of components
// via require.context; neither exists outside the Nova bundle.
globalThis.Nova = {
  booting() {},
  success() {},
  error() {},
  hasComponent: () => true,
  __: key => key,
}

config.global.mocks = { __: key => key }

config.global.stubs = {
  DefaultField: {
    props: ['field', 'errors', 'showHelpText', 'fullWidthContent'],
    template: '<div class="default-field"><slot name="field" /></div>',
  },
  PanelItem: {
    props: ['index', 'field'],
    template: '<div class="panel-item"><slot name="value" /></div>',
  },
  Tooltip: { template: '<div><slot /><slot name="content" /></div>' },
  HelpText: { template: '<div><slot /></div>' },
}

config.global.directives = {
  tooltip: {},
}
