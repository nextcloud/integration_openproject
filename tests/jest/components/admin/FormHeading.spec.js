/**
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, mount } from '@vue/test-utils'
import FormHeading from '../../../../src/components/admin/FormHeading.vue'

const localVue = createLocalVue()

global.t = (app, text) => text

describe('FormHeading.vue', () => {
	describe('is complete prop', () => {
		it('should show checkmark icon, add green title and hide the index if complete', () => {
			const wrapper = getWrapper({
				isComplete: true,
			})
			expect(wrapper).toMatchSnapshot()
		})
		it('should hide the checkmark icon and show the index if not complete', () => {
			const wrapper = getWrapper({
				isComplete: false,
			})
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe('is disabled prop', () => {
		it('should add disabled class to the form heading', () => {
			const wrapper = getWrapper({
				isDisabled: true,
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
