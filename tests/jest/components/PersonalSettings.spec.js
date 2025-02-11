/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2021-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount, createLocalVue, mount } from '@vue/test-utils'
import PersonalSettings from '../../../src/components/PersonalSettings.vue'
import * as dialogs from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { AUTH_METHOD } from '../../../src/utils.js'

const localVue = createLocalVue()

jest.mock('@nextcloud/initial-state', () => {
	const originalModule = jest.requireActual('@nextcloud/initial-state')
	return {
		__esModule: true,
		...originalModule,
		default: jest.fn(),
		loadState: jest.fn(() => {
			return {
				openproject_instance_url: null,
				oauth_client_id: null,
				oauth_client_secret: null,
			}
		}),
	}
})

jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

describe('PersonalSettings.vue', () => {
	const oAuthButtonSelector = 'oauthconnectbutton-stub'
	const oAuthDisconnectButtonSelector = '.openproject-prefs--disconnect'
	const connectedInfoSelector = '.openproject-prefs--connected'
	const connectedAsLabelSelector = `${connectedInfoSelector} label`
	const personalSettingsFormSelector = '.openproject-prefs--form'
	const personalEnableNavigationSelector = '#openproject-prefs--link'
	const personalEnableSearchSelector = '#openproject-prefs--u-search'
	const userGuideIntegrationDocumentationLinkSelector = '.settings--documentation-info'
	const errorLabelSelector = 'errorlabel-stub'
	let wrapper

	beforeEach(() => {
		wrapper = shallowMount(PersonalSettings, {
			localVue,
			mocks: {
				t: (app, msg) => msg,
				generateUrl() {
					return '/'
				},
			},
		})
	})

	it('should show show user guide documentation link', () => {
		const wrapper = getMountedWrapper()
		const userGuideIntegrationDocumentationLink = wrapper.find(userGuideIntegrationDocumentationLinkSelector)
		expect(userGuideIntegrationDocumentationLink.text()).toBe('Learn how to get the most out of the OpenProject integration by visiting our {htmlLink}.')
	})

	describe('oAuth', () => {
		// common state
		const commonState = { authorization_method: AUTH_METHOD.OAUTH2 }
		afterEach(() => {
			delete commonState.admin_config_ok
		})

		describe('when the admin config is okay', () => {
			beforeEach(() => {
				commonState.admin_config_ok = true
			})

			describe.each([
				{ user_name: 'test', token: '' },
				{ user_name: 'test', token: null },
				{ user_name: 'test', token: undefined },
				{ user_name: '', token: '123' },
				{ user_name: null, token: '123' },
				{ user_name: undefined, token: '123' },
				{ user_name: '', token: '' },
				{ user_name: null, token: '' },
				{ user_name: '', token: null },
			])('when username or token not given', (cases) => {
				beforeEach(async () => {
					await wrapper.setData({
						state: {
							...commonState,
							...cases,
						},
					})
				})
				it('oAuth connect button is displayed', () => {
					expect(wrapper.find(oAuthButtonSelector).exists()).toBeTruthy()
					expect(wrapper.find(errorLabelSelector).exists()).toBeFalsy()
				})
				it('personal settings form is not displayed', () => {
					expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
				})
				it('oAuth disconnect button is not displayed', () => {
					expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
				})
			})
			describe('when username and token are given', () => {
				beforeEach(async () => {
					await wrapper.setData({
						state: { ...commonState, user_name: 'test', token: '123' },
					})
				})
				it('oAuth connect button is not displayed', () => {
					expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
				})
				it('oAuth disconnect button is displayed', () => {
					expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeTruthy()
				})
				it('connected as label is displayed', () => {
					expect(wrapper.find(connectedAsLabelSelector).text()).toBe('Connected as {user}')
				})
				it('personal settings form is displayed', () => {
					expect(wrapper.find(personalSettingsFormSelector).exists()).toBeTruthy()
					expect(wrapper.find(errorLabelSelector).exists()).toBeFalsy()
				})
			})

		})
		describe('when the admin config is not okay', () => {
			beforeEach(async () => {
				commonState.admin_config_ok = false
				await wrapper.setData({
					state: { ...commonState, user_name: 'test', token: '123' },
				})
			})
			it('should set proper props to the oauth connect component', () => {
				expect(wrapper.find(oAuthButtonSelector).exists()).toBeTruthy()
				expect(wrapper.find(oAuthButtonSelector).props()).toMatchObject({
					isAdminConfigOk: false,
				})
				expect(wrapper.find(errorLabelSelector).exists()).toBeFalsy()
			})
		})
	})

	describe('OIDC', () => {
		// common state
		const commonState = { authorization_method: AUTH_METHOD.OIDC }
		afterEach(() => {
			delete commonState.admin_config_ok
		})

		describe('when the admin config is okay', () => {
			beforeEach(() => {
				commonState.admin_config_ok = true
			})
			afterEach(() => {
				delete commonState.oidc_user
			})

			describe('OIDC user', () => {
				beforeEach(() => {
					commonState.oidc_user = true
				})

				describe.each([
					{ user_name: 'test', token: '' },
					{ user_name: 'test', token: null },
					{ user_name: 'test', token: undefined },
					{ user_name: '', token: '123' },
					{ user_name: null, token: '123' },
					{ user_name: undefined, token: '123' },
					{ user_name: '', token: '' },
					{ user_name: null, token: '' },
					{ user_name: '', token: null },
				])('when username or token not given', (cases) => {
					beforeEach(async () => {
						await wrapper.setData({
							state: {
								...commonState,
								...cases,
							},
						})
					})
					it(`should show unauthorized error: ${JSON.stringify(cases)}`, () => {
						const errorLabel = wrapper.find(errorLabelSelector)
						expect(errorLabel.exists()).toBeTruthy()
						expect(errorLabel.attributes('error')).toBe('Unauthorized to connect to OpenProject')

						expect(wrapper.find(connectedInfoSelector).exists()).toBeFalsy()
						expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
					})
				})

				describe('when username and token are given', () => {
					beforeEach(async () => {
						await wrapper.setData({
							state: { ...commonState, user_name: 'test', token: '123' },
						})
					})
					it('connected info and personal settings form are displayed', () => {
						// see states
						expect(wrapper.find(connectedAsLabelSelector).text()).toBe('Connected as {user}')
						expect(wrapper.find(personalSettingsFormSelector).exists()).toBeTruthy()

						expect(wrapper.find(errorLabelSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
					})
				})
			})

			describe('non OIDC user', () => {
				beforeEach(() => {
					commonState.oidc_user = false
				})

				describe.each([
					{ user_name: 'test', token: '' },
					{ user_name: 'test', token: null },
					{ user_name: 'test', token: undefined },
					{ user_name: '', token: '123' },
					{ user_name: null, token: '123' },
					{ user_name: undefined, token: '123' },
					{ user_name: '', token: '' },
					{ user_name: null, token: '' },
					{ user_name: '', token: null },
				])('when username or token not given', (cases) => {
					beforeEach(async () => {
						await wrapper.setData({
							state: {
								...commonState,
								...cases,
							},
						})
					})
					it(`should show feature not available error: ${JSON.stringify(cases)}`, () => {
						const errorLabel = wrapper.find(errorLabelSelector)
						expect(errorLabel.exists()).toBeTruthy()
						expect(errorLabel.attributes('error')).toBe('This feature is not available for this user account')

						expect(wrapper.find(connectedInfoSelector).exists()).toBeFalsy()
						expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
					})
				})

				describe('when username and token are given', () => {
					beforeEach(async () => {
						await wrapper.setData({
							state: { ...commonState, user_name: 'test', token: '123' },
						})
					})
					it('should show feature not available error', () => {
						const errorLabel = wrapper.find(errorLabelSelector)
						expect(errorLabel.exists()).toBeTruthy()
						expect(errorLabel.attributes('error')).toBe('This feature is not available for this user account')

						expect(wrapper.find(connectedInfoSelector).exists()).toBeFalsy()
						expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
						expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
					})
				})
			})
		})

		describe.each([{ oidc_user: false }, { oidc_user: true }])('when the admin config is not okay', (states) => {
			beforeEach(async () => {
				commonState.admin_config_ok = false
				await wrapper.setData({
					state: { ...commonState, user_name: 'test', token: '123', ...states },
				})
			})
			it(`should show error message: ${JSON.stringify(states)}`, () => {
				let error = 'This feature is not available for this user account'
				if (states.oidc_user) {
					error = 'Unauthorized to connect to OpenProject'
				}

				const errorLabel = wrapper.find(errorLabelSelector)
				expect(errorLabel.exists()).toBeTruthy()
				expect(errorLabel.attributes('error')).toBe(error)

				expect(wrapper.find(connectedInfoSelector).exists()).toBeFalsy()
				expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
				expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
				expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
			})
		})
	})

	describe.each([{ authorization_method: AUTH_METHOD.OAUTH2, oidc_user: false }, { authorization_method: AUTH_METHOD.OIDC, oidc_user: true }])('user settings', (states) => {
		let saveDefaultsSpy, showSuccessSpy
		beforeEach(() => {
			showSuccessSpy = dialogs.showSuccess.mockImplementationOnce()
			saveDefaultsSpy = jest.spyOn(axios, 'put').mockImplementationOnce(() => Promise.resolve({ data: [] }))
		})

		afterEach(() => {
			saveDefaultsSpy.mockClear()
			saveDefaultsSpy.mockReset()
			saveDefaultsSpy.mockRestore()
			showSuccessSpy.mockClear()
			showSuccessSpy.mockReset()
			showSuccessSpy.mockRestore()
		})

		it(`should be enabled if the admin has enabled the settings: ${states.authorization_method}`, async () => {
			await wrapper.setData({
				state: {
					user_name: 'test',
					token: '123',
					admin_config_ok: true,
					navigation_enabled: true,
					search_enabled: true,
					notification_enabled: true,
					...states,
				},
			})
			expect(wrapper.find(personalSettingsFormSelector).element).toMatchSnapshot()
		})

		it(`should be disabled if the admin has not enabled the settings: ${states.authorization_method}`, async () => {
			await wrapper.setData({
				state: {
					user_name: 'test',
					token: '123',
					admin_config_ok: true,
					navigation_enabled: false,
					search_enabled: false,
					notification_enabled: false,
					...states,
				},
			})
			expect(wrapper.find(personalSettingsFormSelector).element).toMatchSnapshot()
		})

		it(`should send only one request if only one is enabled by the user: ${states.authorization_method}`, async () => {
			const wrapper = getMountedWrapper({
				state: {
					user_name: 'test',
					token: '123',
					admin_config_ok: true,
					navigation_enabled: false,
					search_enabled: false,
					notification_enabled: false,
					...states,
				},
			})

			let personalEnableNavigation = wrapper.find(personalEnableNavigationSelector)
			await personalEnableNavigation.trigger('click')
			personalEnableNavigation = wrapper.find(personalEnableNavigationSelector)
			expect(saveDefaultsSpy).toBeCalledTimes(1)
			expect(saveDefaultsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/config',
				{
					values: {
						navigation_enabled: '1',
					},
				},
			)
			expect(showSuccessSpy).toBeCalledTimes(1)
			expect(showSuccessSpy).toBeCalledWith('OpenProject options saved')
		})

		it(`admin and user should be able to enable and disable the setting simultaneously: ${states.authorization_method}`, async () => {
			const wrapper = getMountedWrapper({
				state: {
					user_name: 'test',
					token: '123',
					admin_config_ok: true,
					navigation_enabled: true,
					search_enabled: false,
					notification_enabled: true,
					...states,
				},
			})

			let personalEnableSearch = wrapper.find(personalEnableSearchSelector)
			await personalEnableSearch.trigger('click')
			personalEnableSearch = wrapper.find(personalEnableSearchSelector)
			expect(saveDefaultsSpy).toBeCalledTimes(1)
			expect(saveDefaultsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/config',
				{
					values: {
						search_enabled: '1',
					},
				},
			)
			expect(showSuccessSpy).toBeCalledTimes(1)
			expect(showSuccessSpy).toBeCalledWith('OpenProject options saved')
			expect(wrapper.find(personalSettingsFormSelector).element).toMatchSnapshot()
			await wrapper.setData({
				state: {
					navigation_enabled: false,
					search_enabled: true,
					notification_enabled: false,
					...states,
				},
			})
			expect(wrapper.find(personalSettingsFormSelector).element).toMatchSnapshot()
		})
	})
})

function getMountedWrapper(data = {}) {
	return mount(PersonalSettings, {
		localVue,
		attachTo: document.body,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		data() {
			return {
				...data,
			}
		},
	})
}
