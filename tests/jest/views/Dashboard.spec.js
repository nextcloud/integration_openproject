/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
// eslint-disable-next-line n/no-unpublished-import
import flushPromises from 'flush-promises'
import util from 'util'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import Dashboard from '../../../src/views/Dashboard.vue'
import { STATE, AUTH_METHOD, checkOauthConnectionResult } from '../../../src/utils.js'
import notificationsResponse from '../fixtures/notificationsResponse.json'
import { messages } from '../../../src/constants/messages.js'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))
jest.mock('@nextcloud/initial-state', () => {
	const originalModule = jest.requireActual('@nextcloud/initial-state')
	return {
		__esModule: true,
		...originalModule,
		default: jest.fn(),
		loadState: jest.fn(() => ({ version: '32' })),
	}
})
jest.mock('../../../src/utils.js', () => ({
	...jest.requireActual('../../../src/utils.js'),
	checkOauthConnectionResult: jest.fn(),
}))

global.OCA = {}
global.OC = {}
const localVue = createLocalVue()

// url
const opUrl = generateOcsUrl('/apps/integration_openproject/api/v1/url')
const notificationUrl = generateOcsUrl('/apps/integration_openproject/api/v1/notifications')
const wpNotificationsUrl = generateOcsUrl('/apps/integration_openproject/api/v1/work-packages/%s/notifications')

describe('Dashboard.vue', () => {
	const errorLabelSelector = 'errorlabel-stub'
	const emptyContentSelector = 'emptycontent-stub'

	const defaultState = {
		authMethods: AUTH_METHOD,
	}

	let spyAxiosGet, spyLaunchLoop

	beforeEach(async () => {
		spyAxiosGet = jest.spyOn(axios, 'get')
			.mockImplementation(getAxiosGetMockFn())
		spyLaunchLoop = jest.spyOn(Dashboard.methods, 'launchLoop')
	})
	afterEach(() => {
		jest.clearAllMocks()
		jest.restoreAllMocks()
	})

	describe('auth method: OAUTH2', () => {
		const commonState = {
			...defaultState,
			authMethod: AUTH_METHOD.OAUTH2,
			oidc_user: false,
			userHasOidcToken: false,
		}
		describe('admin config is not ok', () => {
			it('should show empty content', async () => {
				const wrapper = getWrapper({
					oauthConnectionErrorMessage: '',
					oauthConnectionResult: '',
					isAdminConfigOk: false,
					...commonState,
				})
				await flushPromises()

				expect(wrapper.vm.state).toBe(STATE.ERROR)
				const emptyContent = wrapper.find(emptyContentSelector)
				expect(emptyContent.exists()).toBe(true)
				expect(emptyContent.attributes().state).toBe(STATE.ERROR)
				expect(emptyContent.attributes().authmethod).toBe(AUTH_METHOD.OAUTH2)
				expect(emptyContent.attributes().dashboard).toBe('true')
				expect(emptyContent.attributes().isadminconfigok).toBeFalsy()

				expect(wrapper.find(errorLabelSelector).exists()).toBe(false)
				expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
				expect(spyAxiosGet).not.toBeCalled()
				expect(checkOauthConnectionResult).not.toBeCalled()
			})
		})

		describe('admin config is ok', () => {
			describe('not connected to OP', () => {
				beforeEach(async () => {
					spyAxiosGet.mockRestore()
					spyAxiosGet = jest.spyOn(axios, 'get')
						// eslint-disable-next-line prefer-promise-reject-errors
						.mockImplementation(getAxiosGetMockFn(Promise.reject({ response: { status: 401 } })))
				})
				it('should show empty content', async () => {
					const wrapper = getWrapper({
						oauthConnectionErrorMessage: '',
						oauthConnectionResult: '',
						isAdminConfigOk: true,
						...commonState,
					})
					await flushPromises()

					expect(wrapper.vm.state).toBe(STATE.NO_TOKEN)
					const emptyContent = wrapper.find(emptyContentSelector)
					expect(emptyContent.exists()).toBe(true)
					expect(emptyContent.attributes().state).toBe(STATE.NO_TOKEN)
					expect(emptyContent.attributes().authmethod).toBe(AUTH_METHOD.OAUTH2)
					expect(emptyContent.attributes().dashboard).toBe('true')
					expect(emptyContent.attributes().isadminconfigok).toBe('true')

					expect(wrapper.find(errorLabelSelector).exists()).toBe(false)
					expect(spyAxiosGet).toBeCalledWith(opUrl)
					expect(spyAxiosGet).toBeCalledWith(notificationUrl)
					expect(checkOauthConnectionResult).toHaveBeenCalledTimes(1)
				})
			})

			describe('connected to OP', () => {
				it('should show the notification items', async () => {
					const wrapper = getWrapper({
						oauthConnectionErrorMessage: 'some-token',
						oauthConnectionResult: 'success',
						isAdminConfigOk: true,
						...commonState,
					})
					await flushPromises()

					expect(spyAxiosGet).toBeCalledWith(opUrl)
					expect(spyAxiosGet).toBeCalledWith(notificationUrl)
					expect(wrapper.vm.state).toBe(STATE.OK)
					expect(checkOauthConnectionResult).toHaveBeenCalledTimes(1)
					expect(wrapper.vm.items).toMatchSnapshot()
				})
			})
		})
	})

	describe('auth method: OIDC', () => {
		const commonState = {
			...defaultState,
			oauthConnectionErrorMessage: '',
			oauthConnectionResult: '',
			authMethod: AUTH_METHOD.OIDC,
		}

		describe('OIDC user', () => {
			const localState = { ...commonState, oidc_user: true }
			describe('admin config is not ok', () => {
				it('should show empty content', async () => {
					const wrapper = getWrapper({
						isAdminConfigOk: false,
						...localState,
					})
					await flushPromises()

					expect(wrapper.vm.state).toBe(STATE.ERROR)
					const emptyContent = wrapper.find(emptyContentSelector)
					expect(emptyContent.exists()).toBe(true)
					expect(emptyContent.attributes().state).toBe(STATE.ERROR)
					expect(emptyContent.attributes().authmethod).toBe(AUTH_METHOD.OIDC)
					expect(emptyContent.attributes().dashboard).toBe('true')
					expect(emptyContent.attributes().isadminconfigok).toBeFalsy()

					expect(wrapper.find(errorLabelSelector).exists()).toBe(false)
					expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
					expect(spyAxiosGet).not.toBeCalled()
					expect(checkOauthConnectionResult).not.toBeCalled()
				})
			})

			describe('admin config is ok', () => {
				describe('not connected to OP', () => {
					beforeEach(async () => {
						spyAxiosGet.mockRestore()
						spyAxiosGet = jest.spyOn(axios, 'get')
							// eslint-disable-next-line prefer-promise-reject-errors
							.mockImplementation(getAxiosGetMockFn(Promise.reject({ response: { status: 401 } })))
					})
					it('should show unauthorized error', async () => {
						const wrapper = getWrapper({
							...localState,
							isAdminConfigOk: true,
							userHasOidcToken: false,
						})
						await flushPromises()

						expect(wrapper.vm.state).toBe(STATE.NO_TOKEN)
						expect(wrapper.find(errorLabelSelector).attributes().error).toBe(messages.opConnectionUnauthorized)

						expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
						expect(wrapper.find(emptyContentSelector).exists()).toBe(false)
						expect(spyAxiosGet).toBeCalledWith(opUrl)
						expect(spyAxiosGet).toBeCalledWith(notificationUrl)
						expect(checkOauthConnectionResult).not.toBeCalled()
					})
				})

				describe('connected to OP', () => {
					describe('invalid token', () => {
						beforeEach(async () => {
							spyAxiosGet.mockRestore()
							spyAxiosGet = jest.spyOn(axios, 'get')
							// eslint-disable-next-line prefer-promise-reject-errors
								.mockImplementation(getAxiosGetMockFn(Promise.reject({ response: { status: 401 } })))
						})
						it('should show unauthorized error if token is not valid', async () => {
							const wrapper = getWrapper({
								...localState,
								isAdminConfigOk: true,
								userHasOidcToken: true,
							})
							await flushPromises()

							expect(wrapper.vm.state).toBe(STATE.NO_TOKEN)
							expect(wrapper.find(errorLabelSelector).attributes().error).toBe(messages.opConnectionUnauthorized)

							expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
							expect(wrapper.find(emptyContentSelector).exists()).toBe(false)
							expect(spyAxiosGet).toBeCalledWith(opUrl)
							expect(spyAxiosGet).toBeCalledWith(notificationUrl)
							expect(checkOauthConnectionResult).not.toBeCalled()
						})
					})

					it('should show the notification items', async () => {
						const wrapper = getWrapper({
							...localState,
							isAdminConfigOk: true,
							userHasOidcToken: true,
						})
						await flushPromises()

						expect(spyAxiosGet).toBeCalledWith(opUrl)
						expect(spyAxiosGet).toBeCalledWith(notificationUrl)
						expect(wrapper.vm.state).toBe(STATE.OK)
						expect(checkOauthConnectionResult).not.toBeCalled()
						expect(wrapper.vm.items).toMatchSnapshot()
					})
				})
			})
		})

		describe('non OIDC user', () => {
			const localState = { ...commonState, userHasOidcToken: false, oidc_user: false }
			describe('admin config is not ok', () => {
				it('should show empty content', async () => {
					const wrapper = getWrapper({
						isAdminConfigOk: false,
						...localState,
					})
					await flushPromises()

					expect(wrapper.vm.state).toBe(STATE.ERROR)
					const emptyContent = wrapper.find(emptyContentSelector)
					expect(emptyContent.exists()).toBe(true)
					expect(emptyContent.attributes().state).toBe(STATE.ERROR)
					expect(emptyContent.attributes().authmethod).toBe(AUTH_METHOD.OIDC)
					expect(emptyContent.attributes().dashboard).toBe('true')
					expect(emptyContent.attributes().isadminconfigok).toBeFalsy()

					expect(wrapper.find(errorLabelSelector).exists()).toBe(false)
					expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
					expect(spyAxiosGet).not.toBeCalled()
					expect(checkOauthConnectionResult).not.toBeCalled()
				})
			})

			describe('admin config is ok', () => {
				it('should show feature not available error', async () => {
					const wrapper = getWrapper({
						isAdminConfigOk: true,
						...localState,
					})
					await flushPromises()

					expect(wrapper.find(errorLabelSelector).attributes().error).toBe(messages.featureNotAvailable)

					expect(spyLaunchLoop).toHaveBeenCalledTimes(1)
					expect(wrapper.find(emptyContentSelector).exists()).toBe(false)
					expect(checkOauthConnectionResult).not.toBeCalled()
					expect(spyAxiosGet).not.toBeCalled()
				})
			})
		})
	})

	describe('mark as read', () => {
		const commonState = {
			...defaultState,
			authMethod: AUTH_METHOD.OAUTH2,
			oidc_user: false,
			userHasOidcToken: false,
		}
		const notification = { id: 1 }

		let spyAxiosDelete, spyFetchNotifications
		beforeEach(async () => {
			spyAxiosDelete = jest.spyOn(axios, 'delete')
				.mockImplementation(() => Promise.resolve({}))
			spyFetchNotifications = jest.spyOn(Dashboard.methods, 'fetchNotifications')
		})
		afterEach(() => {
			jest.clearAllMocks()
			jest.restoreAllMocks()
		})

		it('should re-fetch notifications on success', async () => {
			const wrapper = getWrapper({
				oauthConnectionErrorMessage: 'some-token',
				oauthConnectionResult: 'success',
				isAdminConfigOk: true,
				...commonState,
			})
			await flushPromises()
			wrapper.vm.onMarkAsRead(notification)
			await flushPromises()

			expect(spyAxiosDelete).toBeCalledWith(util.format(wpNotificationsUrl, notification.id))
			expect(spyFetchNotifications).toHaveBeenCalledTimes(2)
			expect(showSuccess).toHaveBeenCalledTimes(1)
			expect(showError).toHaveBeenCalledTimes(0)
		})
		it('should show error toast message on failure', async () => {
			spyAxiosDelete = jest.spyOn(axios, 'delete')
			// eslint-disable-next-line prefer-promise-reject-errors
				.mockImplementation(() => Promise.reject('mark as read failed'))
			const wrapper = getWrapper({
				oauthConnectionErrorMessage: 'some-token',
				oauthConnectionResult: 'success',
				isAdminConfigOk: true,
				...commonState,
			})
			await flushPromises()
			wrapper.vm.onMarkAsRead(notification)
			await flushPromises()

			expect(spyAxiosDelete).toBeCalledWith(util.format(wpNotificationsUrl, notification.id))
			expect(spyFetchNotifications).toHaveBeenCalledTimes(1)
			expect(showError).toHaveBeenCalledTimes(1)
			expect(showSuccess).toHaveBeenCalledTimes(0)
		})
	})
})

function getAxiosGetMockFn(notificationResponse) {
	if (!notificationResponse) {
		notificationResponse = Promise.resolve({ data: { ocs: { data: notificationsResponse } } })
	}
	return (url) => {
		switch (url) {
		case opUrl:
			return Promise.resolve({ data: { ocs: { data: 'http://openproject.org' } } })
		case notificationUrl:
			return notificationResponse
		default:
			return Promise.reject(new Error('unexpected url'))
		}
	}
}

function getWrapper(data = {}) {
	return shallowMount(
		Dashboard,
		{
			localVue,
			mocks: {
				t: (app, msg) => msg,
			},
			propsData: {
				title: 'dashboard',
			},
			data: () => data,
		})
}
