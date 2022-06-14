import { createLocalVue, mount } from '@vue/test-utils'
import FieldValue from '../../../../src/components/admin/FieldValue'

const localVue = createLocalVue()

const selectors = {
	inspectButton: '.inspect-btn',
	encryptedValue: '[data-test-id="encrypted-value"]',
}

describe('FieldValue', () => {
	describe('is required prop', () => {
		it('should append asterik with the title if set', () => {
			const wrapper = getWrapper({ isRequired: true })
			expect(wrapper).toMatchSnapshot()
		})
		it('should not append asterik with the title if not set', () => {
			const wrapper = getWrapper({ isRequired: false })
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe('encrypt value prop', () => {
		describe("when set as 'true'", () => {
			it('should render the encrypted value', () => {
				const wrapper = getWrapper({
					encryptValue: true,
				})
				expect(wrapper.find(selectors.encryptedValue)).toMatchSnapshot()
			})
			describe('with inspection prop', () => {
				it('should show the eye icon if set', () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
					})
					expect(wrapper).toMatchSnapshot()
				})
				it('should render the inspect button if both inspect and encrypt props are set', () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
					})
					expect(wrapper.find(selectors.inspectButton).exists()).toBe(true)
				})
				it('should toggle encrypted value when the inspect button is clicked', async () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
					})
					await wrapper.find(selectors.inspectButton).trigger('click')
					expect(wrapper.find(selectors.encryptedValue)).toMatchSnapshot()
					await wrapper.find(selectors.inspectButton).trigger('click')
					expect(wrapper.find(selectors.encryptedValue)).toMatchSnapshot()
				})
				it('should toggle the inspect button icon when the inspect button is clicked', async () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
					})
					await wrapper.find(selectors.inspectButton).trigger('click')
					expect(wrapper.find(selectors.inspectButton)).toMatchSnapshot()
					await wrapper.find(selectors.inspectButton).trigger('click')
					expect(wrapper.find(selectors.inspectButton)).toMatchSnapshot()

				})
			})
		})
		it('should render the value as it is if not set', () => {
			const wrapper = getWrapper()
			expect(wrapper.find(selectors.encryptedValue).exists()).toBe(false)
		})
	})
})

function getWrapper(props = {}) {
	return mount(FieldValue, {
		localVue,
		propsData: {
			title: 'Some Field Title',
			value: 'Some Field Value',
			...props,
		},
		mocks: {
			t: (app, msg) => msg,
		},
	})
}
