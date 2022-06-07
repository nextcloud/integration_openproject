import { createLocalVue, mount } from '@vue/test-utils'
import FormHeading from '../../../../src/components/admin/FormHeading'

const localVue = createLocalVue()

describe("FormHeading", () => {
	describe("is complete prop", () => {
		it("should show checkmark icon, add green title and hide the index if complete", () => {
			const wrapper = getWrapper({
				isComplete: true
			})
			expect(wrapper).toMatchSnapshot()
		})
		it("should hide the checkmark icon and show the index if not complete", () => {
			const wrapper = getWrapper({
				isComplete: false
			})
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe("is disabled prop", () => {
		it("should add disabled class to the form heading", () => {
			const wrapper = getWrapper({
				isDisabled: true
			})
			expect(wrapper).toMatchSnapshot()
		})
	})
})

function getWrapper(props = {}) {
	return mount(FormHeading, {
		localVue,
		propsData: {
			title: 'Some Field Title',
			index: '1',
			...props,
		},
		mocks: {
			t: (app, msg) => msg,
		},
	})
}
