/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount, createLocalVue } from '@vue/test-utils'

import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
import { getCurrentUser } from '@nextcloud/auth'
import OAuthConnectButton from '../../../src/components/OAuthConnectButton.vue'

// mocks
jest.mock('@nextcloud/axios', () => {
	const originalModule = jest.requireActual('@nextcloud/axios')
	return {
		__esModule: true,
		...originalModule,
		default: {
			get: jest.fn(),
			put: jest.fn(),
		},
	}
})
jest.mock('@nextcloud/auth', () => {
	const originalModule = jest.requireActual('@nextcloud/auth')

	return {
		__esModule: true,
		...originalModule,
		default: jest.fn(),
		getCurrentUser: jest.fn().mockReturnValue({ uid: 1234 }),
	}
})
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

const realLocation = global.window.location
const localVue = createLocalVue()

describe('OAuthConnectButton.vue', () => {
	let wrapper
	afterEach(() => {
		Object.defineProperty(global.window, 'location', {
			writable: true,
			value: realLocation,
		})
		jest.clearAllMocks()
	})
	describe('when the admin config is not okay', () => {
		it('should show message for normal user', async () => {
			const returnValue = { isAdmin: false }
			getCurrentUser.mockReturnValue(returnValue)
			wrapper = getWrapper({ isAdminConfigOk: false })
			expect(wrapper).toMatchSnapshot()
		})

		it('should show message for admin user', async () => {
			const returnValue = { isAdmin: true }
			getCurrentUser.mockReturnValue(returnValue)
			wrapper = getWrapper({ isAdminConfigOk: false })
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe('when the admin config is ok', () => {
		beforeEach(() => {
			delete global.window.location
			global.window.location = { replace: jest.fn(), pathname: '/index.php/apps/files/' }
			wrapper = getWrapper()
		})
		describe('on successful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				axios.get.mockImplementationOnce(() =>
					Promise.resolve({ data: 'http://openproject/oauth' }),
				)
				axios.put.mockImplementationOnce(() =>
					Promise.resolve({}),
				)
			})
			it('saves the state to user config', async () => {
				wrapper.find('button').trigger('click')
				await localVue.nextTick()
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
				await localVue.nextTick()
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
				await localVue.nextTick()
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
		localVue,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		propsData: {
			isAdminConfigOk: true,
			...props,
		},
	})
}
