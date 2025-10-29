/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'

import ErrorNote from '../../../../src/components/settings/ErrorNote.vue'

describe('Component: ErrorNote', () => {
	it('should show error title', () => {
		const wrapper = getWrapper({ errorTitle: 'Test error' })

		expect(wrapper.element).toMatchSnapshot()
	})
	it('should show error title and error message', () => {
		const wrapper = getWrapper({ errorTitle: 'Test error', errorMessage: 'Test error message' })

		expect(wrapper.element).toMatchSnapshot()
	})
	it('should show all provided props', () => {
		const wrapper = getWrapper({
			errorTitle: 'Test error',
			errorMessage: 'Test error message',
			errorLink: 'http://example.com',
			errorLinkLabel: 'Test link',
		})

		expect(wrapper.element).toMatchSnapshot()
	})
})

function getWrapper(propsData = {}) {
	return shallowMount(ErrorNote, {
		propsData,
	})
}
