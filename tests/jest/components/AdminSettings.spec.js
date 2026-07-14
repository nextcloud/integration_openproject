/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
import { createLocalVue, shallowMount, mount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings.vue'
import { AUTH_METHOD } from '../../../src/utils.js'

jest.mock('@nextcloud/axios', () => {
	const originalModule = jest.requireActual('@nextcloud/axios')
	return {
		__esModule: true,
		...originalModule,
		default: {
			get: jest.fn(),
			put: jest.fn(),
			post: jest.fn(),
		},
	}
})
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
		loadState: jest.fn(() => {
			return {
				openproject_instance_url: null,
				oauth_client_id: null,
				oauth_client_secret: null,
				version: '32',
			}
		}),
	}
})

const localVue = createLocalVue()

global.OC = {
	dialogs: {
		confirmDestructive: jest.fn(),
		YES_NO_BUTTONS: 70,
	},
}

global.t = (app, text) => text

const writeText = jest.fn()

Object.assign(global.navigator, {
	clipboard: {
	  writeText,
	},
})

const selectors = {
	resetAllAppSettingsButton: '#reset-all-app-settings-btn',
	defaultUserConfigurationsForm: '.default-prefs',
	defaultEnableNavigation: '#default-prefs--link',
	adminAuditNoteCardInfoSelector: '[type="info"]',
}

const completeOAUTH2IntegrationState = {
	openproject_instance_url: 'http://openproject.com',
	authorization_method: AUTH_METHOD.OAUTH2,
	openproject_client_id: 'some-client-id-for-op',
	openproject_client_secret: 'some-client-secret-for-op',
	nc_oauth_client: {
		nextcloud_client_id: 'something',
		nextcloud_client_secret: 'something-else',
	},
	fresh_project_folder_setup: false,
	app_password_set: true,
	project_folder_info: {
		status: true,
	},
	encryption_info: {
		server_side_encryption_enabled: false,
		encryption_enabled_for_groupfolders: false,
	},
}

const appState = {
	apps: {
		oidc: {
			enabled: true,
			supported: true,
			minimum_version: '1.4.0',
			name: 'OIDC Identity Provider',
		},
		user_oidc: {
			enabled: true,
			supported: true,
			minimum_version: '2.0.0',
			name: 'OpenID Connect user backend',
		},
		groupfolders: {
			enabled: true,
			supported: true,
			minimum_version: '1.0.0',
			name: 'Team folders',
		},
	},
}

describe('AdminSettings.vue', () => {
	afterEach(() => {
		jest.restoreAllMocks()
	})

	const commonState = {
		form: {
			serverHost: { complete: true },
			authenticationMethod: {
				value: AUTH_METHOD.OAUTH2,
				complete: true,
			},
			openprojectOauth: { complete: true },
			nextcloudOauth: { complete: true },
			projectFolder: { complete: true },
		},
	}

	describe('reset button', () => {
		it.each([
			{
				openproject_instance_url: 'http://openproject.com',
				authorization_method: AUTH_METHOD.OAUTH2,
				openproject_client_id: 'some-client-id-for-op',
				openproject_client_secret: 'some-client-secret-for-op',
				sso_provider_type: null,
			},
			{
				openproject_instance_url: 'http://openproject.com',
				authorization_method: AUTH_METHOD.OAUTH2,
				openproject_client_id: null,
				openproject_client_secret: null,
				sso_provider_type: null,
			},
			{
				openproject_instance_url: null,
				authorization_method: null,
				openproject_client_id: 'some-client-id-for-op',
				openproject_client_secret: 'some-client-secret-for-op',
				sso_provider_type: null,
			},
			{
				openproject_instance_url: null,
				authorization_method: null,
				openproject_client_id: null,
				openproject_client_secret: 'some-client-secret-for-op',
				sso_provider_type: null,
			},
			{
				openproject_instance_url: 'http://openproject.com',
				authorization_method: AUTH_METHOD.OAUTH2,
				openproject_client_id: null,
				openproject_client_secret: 'some-client-secret-for-op',
				sso_provider_type: null,
			},
			{
				openproject_instance_url: null,
				authorization_method: null,
				openproject_client_id: 'some-client-id-for-op',
				openproject_client_secret: null,
				sso_provider_type: null,
			},
			{
				openproject_instance_url: null,
				authorization_method: null,
				openproject_client_id: '',
				openproject_client_secret: null,
				sso_provider_type: 'nextcloud_hub',
			},
		])('should not be disabled when any of the Open Project setting is set', (value) => {
			const wrapper = getWrapper({
				state: value,
			})
			const resetButton = wrapper.find(selectors.resetAllAppSettingsButton)
			expect(resetButton.attributes('disabled')).toBe(undefined)
		})
		it('should be disabled when no Open Project setting is set', async () => {
			const wrapper = getWrapper({
				state: {
					openproject_instance_url: null,
					authorization_method: null,
					openproject_client_id: null,
					openproject_client_secret: null,
					sso_provider_type: null,
				},
			})
			const resetButton = wrapper.find(selectors.resetAllAppSettingsButton)
			expect(resetButton.attributes('disabled')).toBe('true')
		})

		describe('reset all app settings', () => {
			let wrapper
			let confirmSpy

			const { location } = window
			delete window.location
			window.location = { reload: jest.fn() }
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: 'some-client-id-for-op',
						openproject_client_secret: 'some-client-secret-for-op',
						nc_oauth_client: {
							nextcloud_client_id: 'something',
							nextcloud_client_secret: 'something-else',
						},
						fresh_project_folder_setup: false,
						app_password_set: true,
						project_folder_info: {
							status: true,
						},
						encryption_info: {
							server_side_encryption_enabled: false,
							encryption_enabled_for_groupfolders: false,
						},
					},
				})
				confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
			})

			afterEach(() => {
				jest.clearAllMocks()
			})

			it('should trigger confirm dialog on click', async () => {
				const resetButton = wrapper.find(selectors.resetAllAppSettingsButton)
				await resetButton.trigger('click')
				const expectedConfirmText = 'Are you sure that you want to reset this app '
					+ 'and delete all settings and all connections of all Nextcloud users to OpenProject?'
				const expectedConfirmOpts = {
					cancel: 'Cancel',
					confirm: 'Yes, reset',
					confirmClasses: 'error',
					type: 70,
				}
				const expectedConfirmTitle = 'Reset OpenProject Integration'

				expect(confirmSpy).toBeCalledTimes(1)
				expect(confirmSpy).toBeCalledWith(
					expectedConfirmText,
					expectedConfirmTitle,
					expectedConfirmOpts,
					expect.any(Function),
					true,
				)
			})
			it('should reset all settings on confirm when project folder is not setup', async () => {
				const saveOPOptionsSpy = jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({ data: true }))
				await wrapper.vm.confirmResetIntegrationSetup()

				expect(saveOPOptionsSpy).toBeCalledWith(
					'http://localhost/apps/integration_openproject/admin-config',
					{
						values: {
							openproject_client_id: null,
							openproject_client_secret: null,
							openproject_instance_url: null,
							authorization_method: null,
							default_enable_navigation: false,
							default_enable_unified_search: false,
							setup_project_folder: null,
							setup_app_password: false,
							oidc_provider: null,
							sso_provider_type: null,
							targeted_audience_client_id: null,
							token_exchange: null,
						},
					},
				)
				axios.put.mockReset()
			})
			it('should reload the window at the end', async () => {
				await wrapper.vm.confirmResetIntegrationSetup()
				await wrapper.vm.$nextTick()
				expect(window.location.reload).toBeCalledTimes(1)
				window.location = location
			})
		})
	})

	describe('default user configurations form', () => {
		it('should be visible when the integration is complete', () => {
			const wrapper = getMountedWrapper({
				state: completeOAUTH2IntegrationState,
				...commonState,
			})
			expect(wrapper.find(selectors.defaultUserConfigurationsForm).element).toMatchSnapshot()
		})
		it('should not be visible if the integration is not complete', () => {
			const wrapper = getMountedWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-for-op',
					openproject_client_secret: 'some-client-secret-for-op',
					nc_oauth_client: null,
					fresh_project_folder_setup: false,
					app_password_set: true,
					project_folder_info: {
						status: true,
					},
					encryption_info: {
						server_side_encryption_enabled: false,
						encryption_enabled_for_groupfolders: false,
					},
				},
			})
			expect(wrapper.find(selectors.defaultUserConfigurationsForm).exists()).toBeFalsy()
		})
		it('should show success message and update the default config on success', async () => {
			dialogs.showSuccess.mockImplementationOnce()
			const saveDefaultsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(() => Promise.resolve({ data: true }))

			const wrapper = getMountedWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-for-op',
					openproject_client_secret: 'some-client-secret-for-op',
					nc_oauth_client: {
						nextcloud_client_id: 'something',
						nextcloud_client_secret: 'something-else',
					},
					fresh_project_folder_setup: false,
					app_password_set: true,
					project_folder_info: {
						status: true,
					},
					encryption_info: {
						server_side_encryption_enabled: false,
						encryption_enabled_for_groupfolders: false,
					},
				},
				...commonState,
			})

			const $defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			await $defaultEnableNavigation.trigger('click')
			expect(saveDefaultsSpy).toBeCalledTimes(1)
			expect(saveDefaultsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/admin-config',
				{
					values: {
						default_enable_navigation: true,
						default_enable_unified_search: false,
					},
				},
			)
			expect(dialogs.showSuccess).toBeCalledTimes(1)
			expect(dialogs.showSuccess).toBeCalledWith('Default user configuration saved')
		})
		it('should show error message on fail response', async () => {
			// mock the dialogs showError method
			dialogs.showError.mockImplementationOnce()

			// mock the axios PUT method for error
			axios.put.mockReset()
			const err = new Error()
			err.message = 'some issue'
			err.response = {}
			err.response.request = {}
			err.response.request.responseText = 'Some message'
			axios.put.mockRejectedValueOnce(err)

			const wrapper = getMountedWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-for-op',
					openproject_client_secret: 'some-client-secret-for-op',
					nc_oauth_client: {
						nextcloud_client_id: 'something',
						nextcloud_client_secret: 'something-else',
					},
					fresh_project_folder_setup: false,
					app_password_set: true,
					project_folder_info: {
						status: true,
					},
					encryption_info: {
						server_side_encryption_enabled: false,
						encryption_enabled_for_groupfolders: false,
					},
				},
				...commonState,
			})
			const $defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			await $defaultEnableNavigation.trigger('click')
			await localVue.nextTick()

			expect(dialogs.showError).toBeCalledTimes(1)
			expect(dialogs.showError).toBeCalledWith('Failed to save default user configuration: Some message')

		})
	})

	describe('terms of service', () => {
		const termsOfServiceComponentStub = 'termsofserviceunsigned-stub'
		const termsOfServiceComponentStubAttribute = 'isalltermsofservicesignedforuseropenproject'
		it('should show modal when terms of services are not signed', () => {
			const wrapper = getWrapper({
				state: {
					all_terms_of_services_signed: true,
				},
			})
			expect(wrapper.find(termsOfServiceComponentStub).attributes(termsOfServiceComponentStubAttribute)).toBe('true')
		})

		it('should not show modal when all terms of services are signed', () => {
			const wrapper = getWrapper({
				state: {
					all_terms_of_services_signed: false,
				},
			})
			expect(wrapper.find(termsOfServiceComponentStub).attributes(termsOfServiceComponentStubAttribute)).toBeFalsy()
		})
	})

	describe('admin audit logging', () => {
		it.each([
			[
				'should show information for admin audit logging configuration',
				{
					admin_audit_configuration_correct: false,
				},
				true,
			],
			[
				'should not show information for admin audit logging configuration',
				{
					admin_audit_configuration_correct: true,
				},
				false,
			],
		])('%s', (name, state, expectedResult) => {
			const wrapper = getWrapper({ state })
			const adminAuditLogNoteCard = wrapper.find(selectors.adminAuditNoteCardInfoSelector)
			expect(adminAuditLogNoteCard.exists()).toBe(expectedResult)
		})
	})
})

function getWrapper(data = {}) {
	return shallowMount(AdminSettings, {
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
				state: {
					...appState,
					...data.state,
				},
			}
		},
	})
}

function getMountedWrapper(data = {}) {
	return mount(AdminSettings, {
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
				state: {
					...appState,
					...data.state,
				},
			}
		},
	})
}
