const passthrough = name => ({
  name,
  inheritAttrs: false,
  props: ['icon', 'leadingIcon', 'variant', 'size', 'state', 'padding', 'type', 'name', 'extraClasses'],
  template: '<button type="button" v-bind="$attrs"><slot /></button>',
})

export const Button = passthrough('Button')
export const Icon = { name: 'Icon', props: ['name', 'type'], template: '<span />' }
export const Badge = { name: 'Badge', props: ['extraClasses'], template: '<span><slot /></span>' }
export const Loader = { name: 'Loader', template: '<span />' }
export const Checkbox = { name: 'Checkbox', template: '<input type="checkbox" />' }
