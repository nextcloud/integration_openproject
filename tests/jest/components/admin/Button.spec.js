import { createLocalVue, mount } from '@vue/test-utils'
import Button from '../../../../src/components/admin/Button'

const localVue = createLocalVue()

describe('Admin Button', () => {
	it('should not show the loading icon or button icon if not set', () => {
		const wrapper = getWrapper()
		expect(wrapper).toMatchSnapshot()
	})
	it('should show the loading icon when "isLoading" is set as "true"', () => {
		const wrapper = getWrapper({
			isLoading: true,
		})
		expect(wrapper).toMatchSnapshot()
	})
	it('should show the icon if the "iconClass" prop is set', () => {
		const wrapper = getWrapper({
			iconClass: 'some class name',
		})
		expect(wrapper).toMatchSnapshot()
	})
	it('should show both button icon and loading icon if set', () => {
		const wrapper = getWrapper({
			iconClass: 'some class name',
			isLoading: true,
		})
		expect(wrapper).toMatchSnapshot()
	})
})

function getWrapper(props = {}) {
	return mount(Button, {
		localVue,
		propsData: {
			text: 'My Button',
			...props,
		},
		mocks: {
			t: (app, msg) => msg,
		},
	})
}
