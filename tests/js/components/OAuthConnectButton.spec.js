/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import flushPromises from 'flush-promises'
import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'

import OAuthConnectButton from '../../../src/components/OAuthConnectButton.vue'

// mocks
vi.mock(import('@nextcloud/axios'), async (importOriginal) => {
	const originalModule = await importOriginal()
	return {
		__esModule: true,
		...originalModule,
		default: {
			get: vi.fn(),
			put: vi.fn(),
		},
	}
})
vi.mock(import('@nextcloud/auth'), async (importOriginal) => {
	const originalModule = await importOriginal()
	return {
		__esModule: true,
		...originalModule,
		default: vi.fn(),
		getCurrentUser: vi.fn().mockReturnValue({ uid: 1234 }),
	}
})
vi.mock(import('@nextcloud/dialogs'), () => ({
	getLanguage: vi.fn(() => ''),
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('@nextcloud/router'), () => ({
	generateUrl: (path) => `http://localhost${path}`,
	generateOcsUrl: (path) => `http://localhost${path}`,
	imagePath: (path) => `http://localhost${path}`,
}))

const realLocation = global.window.location

describe('OAuthConnectButton.vue', () => {
	let wrapper
	afterEach(() => {
		Object.defineProperty(global.window, 'location', {
			writable: true,
			value: realLocation,
		})
	})
	describe('when the admin config is not okay', () => {
		it('should show message for normal user', async () => {
			const returnValue = { isAdmin: false }
			getCurrentUser.mockReturnValue(returnValue)
			wrapper = getWrapper({ isAdminConfigOk: false })
			expect(wrapper.element).toMatchSnapshot()
		})

		it('should show message for admin user', async () => {
			const returnValue = { isAdmin: true }
			getCurrentUser.mockReturnValue(returnValue)
			wrapper = getWrapper({ isAdminConfigOk: false })
			expect(wrapper.element).toMatchSnapshot()
		})
	})
	describe('when the admin config is ok', () => {
		beforeEach(() => {
			delete global.window.location
			global.window.location = { replace: vi.fn(), pathname: '/index.php/apps/files/' }
			wrapper = getWrapper()
		})
		describe('on successful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				axios.get.mockImplementationOnce(() => Promise.resolve({ data: 'http://openproject/oauth' }))
				axios.put.mockImplementationOnce(() => Promise.resolve({}))
			})
			it('saves the state to user config', async () => {
				wrapper.find('button').trigger('click')
				await nextTick()
				expect(axios.put).toHaveBeenCalledWith(
					'http://localhost/apps/integration_openproject/config',
					{
						values: {
							oauth_journey_starting_page: expect.stringMatching(/{.*}/),
						},
					},
				)
			})
			it('redirects to the openproject oauth uri', async () => {
				wrapper.find('button').trigger('click')
				await flushPromises()
				await nextTick()
				expect(window.location.replace).toHaveBeenCalledWith(
					'http://openproject/oauth',
				)
			})
		})
		describe('on unsuccessful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				const err = new Error()
				err.message = 'some issue'
				axios.get.mockRejectedValueOnce(err)
			})
			it('shows an error', async () => {
				dialogs.showError.mockImplementationOnce()
				wrapper.find('button').trigger('click')
				await flushPromises()
				await nextTick()
				expect(dialogs.showError).toHaveBeenCalledWith(
					'Failed to redirect to OpenProject: some issue',
				)
				expect(window.location.replace).not.toHaveBeenCalled()
			})
		})
	})
})

function getWrapper(props = {}) {
	return mount(OAuthConnectButton, {
		global: {
			mocks: {
				t: (app, msg) => msg,
			},
		},
		props: {
			isAdminConfigOk: true,
			...props,
		},
	})
}
