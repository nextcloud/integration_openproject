/**
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import flushPromises from 'flush-promises'

import FormHeading from '../../../../src/components/admin/FormHeading.vue'

describe('FormHeading.vue', () => {
	describe('is complete prop', () => {
		it('should show checkmark icon, add green title and hide the index if complete', () => {
			const wrapper = getWrapper({
				isComplete: true,
			})
			expect(wrapper.element).toMatchSnapshot()
		})
		it('should hide the checkmark icon and show the index if not complete', () => {
			const wrapper = getWrapper({
				isComplete: false,
			})
			expect(wrapper.element).toMatchSnapshot()
		})
	})
	describe('is disabled prop', () => {
		it('should add disabled class to the form heading', () => {
			const wrapper = getWrapper({
				isDisabled: true,
			})
			expect(wrapper.element).toMatchSnapshot()
		})
	})
})

function getWrapper(props = {}) {
	return mount(FormHeading, {
		props: {
			title: 'Some Field Title',
			index: '1',
			...props,
		},
		global: {
			mocks: {
				t: (app, msg) => msg,
			},
		},
	})
}
