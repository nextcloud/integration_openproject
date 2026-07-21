/**
 * SPDX-FileCopyrightText: 2022-2023 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, mount } from '@vue/test-utils'
import FieldValue from '../../../../src/components/admin/FieldValue.vue'

const localVue = createLocalVue()

const selectors = {
	inspectButton: '.eye-icon',
	inspectOffButton: '.eye-off-icon',
	itemValue: '.field-item-value',
}

describe('FieldValue.vue', () => {
	describe('is required prop', () => {
		it('should append asterik with the title if set', () => {
			const wrapper = getWrapper({ isRequired: true })
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should not append asterik with the title if not set', () => {
			const wrapper = getWrapper({ isRequired: false })
			expect(wrapper.element).toMatchSnapshot()
		})
	})
	describe('encrypt value prop', () => {
		describe("when set as 'true'", () => {
			it('should render the encrypted value', () => {
				const wrapper = getWrapper({
					encryptValue: true,
				})
				expect(wrapper.find(selectors.itemValue).element).toMatchSnapshot()
			})
			describe('with inspection prop', () => {
				it('should show the inspect button with the eye icon if set', () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
						inspect: true,
					})
					expect(wrapper.element).toMatchSnapshot()
				})
				it('should toggle encrypted value when the inspect button is clicked', async () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
						inspect: true,
					})
					await wrapper.find(selectors.inspectButton).trigger('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.itemValue).element).toMatchSnapshot()
					await wrapper.find(selectors.inspectOffButton).trigger('click')
					expect(wrapper.find(selectors.itemValue).element).toMatchSnapshot()
				})
				it('should toggle the inspect button icon when the inspect button is clicked', async () => {
					const wrapper = getWrapper({
						encryptValue: true,
						withInspection: true,
						inspect: true,
					})
					const inspect = wrapper.find(selectors.inspectButton)
					await inspect.trigger('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.inspectOffButton).element).toMatchSnapshot()
					const inspectOff = wrapper.find(selectors.inspectOffButton)
					await inspectOff.trigger('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.inspectButton).element).toMatchSnapshot()

				})
			})
		})
		it('should render the actual value as it is if not set', () => {
			const wrapper = getWrapper()
			expect(wrapper.find(selectors.itemValue).element).toMatchSnapshot()
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
