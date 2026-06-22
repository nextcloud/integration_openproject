/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
import { createLocalVue, shallowMount, mount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // eslint-disable-line n/no-unpublished-import
import AdminSettings from '../../../src/components/AdminSettings.vue'
import { F_MODES, AUTH_METHOD } from '../../../src/utils.js'
import { appLinks } from '../../../src/constants/links.js'
import { messagesFmt, messages } from '../../../src/constants/messages.js'

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
	opOauthForm: '.openproject-oauth-values',
	authorizationMethod: '.authorization-method',
	authorizationSettings: '.authorization-settings',
	authorizationMethodSaveButton: '[data-test-id="submit-auth-method-values-btn"]',
	authorizationSettingsSaveButton: '[data-test-id="submit-oidc-auth-settings-values-btn"]',
	providerInput: '#provider-search-input',
	oidcDropDownFirstElement: 'ul [title="keycloak"]',
	authorizationMethodResetButton: '[data-test-id="reset-authorization-method-btn"]',
	authorizationCancelResetButton: '[data-test-id="cancel-edit-auth-method-btn"]',
	authorizationSettingsResetButton: '[data-test-id="reset-auth-settings-btn"]',
	authorizationSettingsCancelButton: '[data-test-id="cancel-edit-auth-setting-btn"]',
	openIdIdentityRadio: '[value="oidc"]',
	oauth2Radio: '[value="oauth2"]',
	openIdIdentityDisabled: 'input[type="radio"][value="oidc"][disabled="disabled"]',
	authSettingTargetAudClient: '#authorization-method-target-client-id input',
	ncOauthForm: '.nextcloud-oauth-values',
	projectFolderSetupForm: '.project-folder-setup',
	resetServerHostButton: '[data-test-id="reset-server-host-btn"]',
	textInputWrapper: '.text-input',
	resetOPOAuthFormButton: '[data-test-id="reset-op-oauth-btn"]',
	resetNcOAuthFormButton: '[data-test-id="reset-nc-oauth-btn"]',
	submitOPOAuthFormButton: '[data-test-id="submit-op-oauth-btn"]',
	opOauthClientIdInput: '#openproject-oauth-client-id',
	opOauthClientSecretInput: '#openproject-oauth-client-secret',
	submitNcOAuthFormButton: '[data-test-id="submit-nc-oauth-values-form-btn"]',
	resetAllAppSettingsButton: '#reset-all-app-settings-btn',
	defaultUserConfigurationsForm: '.default-prefs',
	defaultEnableNavigation: '#default-prefs--link',
	projectFolderFormHeading: '.project-folder-setup formheading-stub',
	projectFolderSetupSwitch: '[type="checkbox"]',
	projectFolderSetupButtonStub: 'nccheckboxradioswitch-stub[type="switch"]',
	completeProjectFolderSetupWithGroupFolderButton: '[data-test-id="complete-with-project-folders-form-btn"]',
	completeWithoutProjectFolderSetupButton: '[data-test-id="complete-without-project-folder-form-btn"]',
	editProjectFolderSetup: '[data-test-id="edit-project-folder-setup"]',
	projectFolderStatus: '.project-folder-status-value',
	projectFolderErrorMessage: '.note-card--title',
	projectFolderErrorMessageDetails: '.note-card--error-description',
	projectFolderErrorNote: '.project-folder-setup errornote-stub',
	userAppPasswordButton: '[data-test-id="reset-user-app-password"]',
	setupIntegrationDocumentationLinkSelector: '.settings--documentation-info',
	adminAuditNoteCardInfoSelector: '[type="info"]',
	encryptionNoteCardWarningSelector: '.project-folder-setup ncnotecard-stub',
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
	const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')

	describe('form mode and completed status without project folder setup for OAUTH2 authorization config', () => {
		it.each([
			[
				'with empty state',
				{
					openproject_instance_url: null,
					authorization_method: null,
					openproject_client_id: null,
					openproject_client_secret: null,
					nc_oauth_client: null,
				},
				{
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.DISABLE,
					projectFolderSetUp: F_MODES.DISABLE,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: false,
					opOauth: false,
					ncOauth: false,
					projectFolderSetUp: false,
					opUserAppPassword: false,
				},
			],
			[
				'with incomplete OpenProject Authorization Method',
				{
					openproject_instance_url: 'https://openproject.example.com',
					authorization_method: null,
					openproject_client_id: null,
					openproject_client_secret: null,
					nc_oauth_client: null,
				},
				{
					authorizationMethod: F_MODES.EDIT,
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.DISABLE,
					projectFolderSetUp: F_MODES.DISABLE,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: false,
					opOauth: false,
					ncOauth: false,
					projectFolderSetUp: false,
					opUserAppPassword: false,
				},
			],
			[
				'with incomplete OpenProject OAuth values',
				{
					openproject_instance_url: 'https://openproject.example.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: null,
					openproject_client_secret: null,
					nc_oauth_client: null,
				},
				{
					authorizationMethod: F_MODES.VIEW,
					opOauth: F_MODES.EDIT,
					ncOauth: F_MODES.DISABLE,
					projectFolderSetUp: F_MODES.DISABLE,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: true,
					opOauth: false,
					ncOauth: false,
					projectFolderSetUp: false,
					opUserAppPassword: false,
				},
			],
			[
				'with complete OpenProject OAuth values',
				{
					openproject_instance_url: 'https://openproject.example.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'abcd',
					openproject_client_secret: 'abcdefgh',
					nc_oauth_client: null,
					fresh_project_folder_setup: true,
				},
				{
					authorizationMethod: F_MODES.VIEW,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.DISABLE,
					projectFolderSetUp: F_MODES.DISABLE,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: true,
					opOauth: true,
					ncOauth: false,
					projectFolderSetUp: false,
					opUserAppPassword: false,
				},
			],
			[
				'with everything but empty OpenProject OAuth values',
				{
					openproject_instance_url: 'https://openproject.example.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: null,
					openproject_client_secret: null,
					nc_oauth_client: {
						nextcloud_client_id: 'some-client-id-here',
						nextcloud_client_secret: 'some-client-secret-here',
					},
				},
				{
					authorizationMethod: F_MODES.VIEW,
					opOauth: F_MODES.EDIT,
					ncOauth: F_MODES.VIEW,
					projectFolderSetUp: F_MODES.VIEW,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: true,
					opOauth: false,
					ncOauth: true,
					projectFolderSetUp: true,
					opUserAppPassword: false,
				},
			],
			[
				'with a complete admin settings',
				{
					openproject_instance_url: 'https://openproject.example.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'client-id-here',
					openproject_client_secret: 'client-id-here',
					nc_oauth_client: {
						nextcloud_client_id: 'nc-client-id-here',
						nextcloud_client_secret: 'nc-client-secret-here',
					},
				},
				{
					authorizationMethod: F_MODES.VIEW,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.VIEW,
					projectFolderSetUp: F_MODES.VIEW,
					opUserAppPassword: F_MODES.DISABLE,
				},
				{
					authorizationMethod: true,
					opOauth: true,
					ncOauth: true,
					projectFolderSetUp: true,
					opUserAppPassword: false,
				},
			],
		])('when the form is loaded %s', (name, state, expectedFormMode, expectedFormState) => {
			const wrapper = getWrapper({ state })
			expect(wrapper.vm.formMode.opOauth).toBe(expectedFormMode.opOauth)
			expect(wrapper.vm.formMode.ncOauth).toBe(expectedFormMode.ncOauth)
			expect(wrapper.vm.formMode.projectFolderSetUp).toBe(expectedFormMode.projectFolderSetUp)
			expect(wrapper.vm.formMode.opUserAppPassword).toBe(expectedFormMode.opUserAppPassword)

			expect(wrapper.vm.isFormCompleted.opOauth).toBe(expectedFormState.opOauth)
			expect(wrapper.vm.isFormCompleted.ncOauth).toBe(expectedFormState.ncOauth)
			expect(wrapper.vm.isFormCompleted.projectFolderSetUp).toBe(expectedFormState.projectFolderSetUp)
			expect(wrapper.vm.isFormCompleted.opUserAppPassword).toBe(expectedFormState.opUserAppPassword)
		})
	})

	describe('OpenProject OAuth values form', () => {
		describe('view mode and completed state', () => {
			let wrapper, opOAuthForm, resetButton
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(() => Promise.resolve({ data: { status: true, oPOAuthTokenRevokeStatus: '' } }))
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: 'openproject-client-id',
						openproject_client_secret: 'openproject-client-secret',
						nc_oauth_client: null,
					},
					form: {
						serverHost: {
							complete: true,
							value: 'http://openproject.com',
						},
						authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
					},
				})
				opOAuthForm = wrapper.find(selectors.opOauthForm)
				resetButton = opOAuthForm.find(selectors.resetOPOAuthFormButton)
			})
			it('should show field values and hide the form if server host form is complete', () => {
				expect(opOAuthForm).toMatchSnapshot()
			})
			describe('reset button', () => {
				it('should trigger confirm dialog on click', async () => {
					await resetButton.trigger('click')
					expect(confirmSpy).toBeCalledTimes(1)

					const expectedDialogMessage = 'If you proceed you will need to update these settings with the new'
						+ ' OpenProject OAuth credentials. Also, all users will need to reauthorize'
						+ ' access to their OpenProject account.'
					const expectedDialogTitle = 'Replace OpenProject OAuth values'
					const expectedDialogOpts = {
						cancel: 'Cancel',
						confirm: 'Yes, replace',
						confirmClasses: 'error',
						type: 70,
					}
					expect(confirmSpy).toHaveBeenCalledWith(
						expectedDialogMessage,
						expectedDialogTitle,
						expectedDialogOpts,
						expect.any(Function),
						true,
					)
					jest.clearAllMocks()
					wrapper.destroy()
				})
				it('should clear values on confirm', async () => {
					jest.clearAllMocks()
					await wrapper.vm.clearOPOAuthClientValues()

					expect(saveOPOptionsSpy).toBeCalledTimes(1)
					expect(wrapper.vm.state.openproject_client_id).toBe(null)
				})
			})
		})
		describe('edit mode', () => {
			let wrapper
			beforeEach(() => {
				jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({ data: { status: true } }))
				jest.spyOn(axios, 'post')
					.mockImplementationOnce(() => Promise.resolve({
						data: {
							clientId: 'nc-client-id101',
							clientSecret: 'nc-client-secret101',
						},
					}))
				wrapper = getWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: '',
						openproject_client_secret: '',
						nc_oauth_client: null,
					},
					form: {
						serverHost: {
							complete: true,
							value: 'http://openproject.com',
						},
						authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
					},
				})
			})
			afterEach(() => {
				axios.post.mockReset()
				axios.put.mockReset()
				jest.clearAllMocks()
				wrapper.destroy()
			})
			it('should show the form and hide the field values', () => {
				expect(wrapper.find(selectors.opOauthForm)).toMatchSnapshot()
			})
			describe('submit button', () => {
				it('should be enabled with complete client values', async () => {
					let submitButton
					submitButton = wrapper.find(selectors.submitOPOAuthFormButton)
					expect(submitButton.attributes().disabled).toBe('true')
					await wrapper.find(selectors.opOauthClientIdInput).vm.$emit('input', 'qwerty')
					await wrapper.find(selectors.opOauthClientSecretInput).vm.$emit('input', 'qwerty')

					submitButton = wrapper.find(selectors.submitOPOAuthFormButton)
					expect(submitButton.attributes().disabled).toBe(undefined)
				})
				describe('when clicked', () => {
					describe('when the save is successful', () => {
						beforeEach(async () => {
							jest.spyOn(wrapper.vm, 'saveOPOptions').mockReturnValue(true)
							wrapper.find(selectors.opOauthClientIdInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.opOauthClientSecretInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.submitOPOAuthFormButton).vm.$emit('click')
						})
						it('should set the form to view mode', async () => {
							expect(wrapper.vm.formMode.opOauth).toBe(F_MODES.VIEW)
						})
						it('should set the isFormCompleted to true', async () => {
							expect(wrapper.vm.isFormCompleted.opOauth).toBe(true)
						})

						it('should not create Nextcloud OAuth client if already present', async () => {
							jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.resolve({ data: { status: true } }))
							const createNCOAuthClientSpy = jest.spyOn(AdminSettings.methods, 'createNCOAuthClient')
								.mockImplementationOnce(() => jest.fn())
							const wrapper = getMountedWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: {
										nextcloud_client_id: 'abcdefg',
										nextcloud_client_secret: 'slkjdlkjlkd',
									},
								},
								form: {
									serverHost: {
										complete: true,
										value: 'http://openproject.com',
									},
									authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
								},
							})
							await wrapper.find(`${selectors.opOauthClientIdInput} input`).setValue('qwerty')
							await wrapper.find(`${selectors.opOauthClientSecretInput} input`).setValue('qwerty')
							await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')
							expect(createNCOAuthClientSpy).not.toHaveBeenCalled()
						})

						it('should create Nextcloud OAuth client if not already present', async () => {
							jest.spyOn(axios, 'post')
								.mockImplementationOnce(() => Promise.resolve({ data: { status: false } }))
							const createNCOAuthClientSpy = jest.spyOn(AdminSettings.methods, 'createNCOAuthClient')
								.mockImplementationOnce(() => jest.fn())
							const wrapper = getMountedWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: '',
								},
								form: {
									serverHost: {
										complete: true,
										value: 'http://openproject.com',
									},
									authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
								},
							})
							await wrapper.find(`${selectors.opOauthClientIdInput} input`).setValue('qwerty')
							await wrapper.find(`${selectors.opOauthClientSecretInput} input`).setValue('qwerty')
							await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')

							expect(createNCOAuthClientSpy).toBeCalledTimes(1)
						})
					})

					describe('when the save fails', () => {
						beforeEach(async () => {
							jest.spyOn(wrapper.vm, 'saveOPOptions').mockReturnValue(false)
							wrapper.find(selectors.opOauthClientIdInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.opOauthClientSecretInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.submitOPOAuthFormButton).vm.$emit('click')
						})
						it('should set the form to view mode', async () => {
							expect(wrapper.vm.formMode.opOauth).toBe(F_MODES.EDIT)
						})
						it('should set the isFormCompleted to true', async () => {
							expect(wrapper.vm.isFormCompleted.opOauth).toBe(false)
						})

						it('should not create Nextcloud OAuth client', async () => {
							jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.resolve({ data: { status: true } }))
							const createNCOAuthClientSpy = jest.spyOn(AdminSettings.methods, 'createNCOAuthClient')
								.mockImplementationOnce(() => jest.fn())
							const wrapper = getMountedWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: {
										nextcloud_client_id: 'abcdefg',
										nextcloud_client_secret: 'slkjdlkjlkd',
									},
								},
								form: {
									serverHost: {
										complete: true,
										value: 'http://openproject.com',
									},
									authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
								},
							})
							await wrapper.find(`${selectors.opOauthClientIdInput} input`).setValue('qwerty')
							await wrapper.find(`${selectors.opOauthClientSecretInput} input`).setValue('qwerty')
							await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')
							expect(createNCOAuthClientSpy).not.toHaveBeenCalled()
						})
					})

					describe('when the admin config is ok on save options', () => {
						beforeEach(async () => {
							await wrapper.find(selectors.opOauthClientIdInput).vm.$emit('input', 'qwerty')
							await wrapper.find(selectors.opOauthClientSecretInput).vm.$emit('input', 'qwerty')
							await wrapper.find(selectors.submitOPOAuthFormButton).vm.$emit('click')
							await flushPromises()
						})
						it('should set the form to view mode', () => {
							expect(wrapper.vm.formMode.opOauth).toBe(F_MODES.VIEW)
						})
						it('should set the adminConfigStatus as "true"', () => {
							expect(wrapper.vm.isAdminConfigOk).toBe(true)
						})
						it('should create Nextcloud OAuth client if not already present', () => {
							expect(wrapper.vm.state.nc_oauth_client).toMatchObject({
								clientId: 'nc-client-id101',
								clientSecret: 'nc-client-secret101',
							})
						})
						it('should not create Nextcloud OAuth client if already present', async () => {
							jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.resolve({ data: { status: true } }))
							const createNCOAuthClientSpy = jest.spyOn(AdminSettings.methods, 'createNCOAuthClient')
								.mockImplementationOnce(() => jest.fn())
							const wrapper = getWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: {
										nextcloud_client_id: 'abcdefg',
										nextcloud_client_secret: 'slkjdlkjlkd',
									},
								},
								form: {
									serverHost: {
										complete: true,
										value: 'http://openproject.com',
									},
									authenticationMethod: { complete: true, value: AUTH_METHOD.OAUTH2 },
								},
							})
							wrapper.find(selectors.opOauthClientIdInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.opOauthClientSecretInput).vm.$emit('input', 'qwerty')
							wrapper.find(selectors.submitOPOAuthFormButton).vm.$emit('click')
							await flushPromises()
							expect(createNCOAuthClientSpy).not.toHaveBeenCalled()
						})

						it('should not create new user app password if already present', async () => {
							const saveOPOptionsSpy = jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.resolve({ data: { oPUserAppPassword: null } }))
							const wrapper = getMountedWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: {
										nextcloud_client_id: 'abcdefg',
										nextcloud_client_secret: 'slkjdlkjlkd',
									},
									fresh_project_folder_setup: false,
									project_folder_info: {
										status: true,
									},
									app_password_set: false,
								},
								oPUserAppPassword: 'opUserPassword',
							})
							expect(saveOPOptionsSpy).toBeCalledWith(
								'http://localhost/apps/integration_openproject/admin-config',
								{
									values: {
										openproject_client_id: 'qwerty',
										openproject_client_secret: 'qwerty',
									},
								},
							)
							expect(wrapper.vm.oPUserAppPassword).toBe('opUserPassword')
						})
					})
				})
			})
		})
	})

	describe('Nextcloud OAuth values form', () => {
		describe('view mode with complete values', () => {
			it('should show the field values and hide the form', () => {
				const wrapper = getWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: 'some-client-id-here',
						openproject_client_secret: 'some-client-secret-here',
						nc_oauth_client: {
							nextcloud_client_id: 'some-nc-client-id-here',
							nextcloud_client_secret: 'some-nc-client-secret-here',
						},
					},
				})
				expect(wrapper.find(selectors.ncOauthForm)).toMatchSnapshot()
			})
			describe('reset button', () => {
				afterEach(() => {
					jest.clearAllMocks()
				})
				it('should trigger the confirm dialog', async () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'op-client-id',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: 'nc-clientid',
								nextcloud_client_secret: 'nc-clientsecret',
							},
						},
					})

					const expectedConfirmText = 'If you proceed you will need to update the settings in your OpenProject '
						+ 'with the new Nextcloud OAuth credentials. Also, all users in OpenProject '
						+ 'will need to reauthorize access to their Nextcloud account.'
					const expectedConfirmOpts = {
						cancel: 'Cancel',
						confirm: 'Yes, replace',
						confirmClasses: 'error',
						type: 70,
					}
					const expectedConfirmTitle = 'Replace Nextcloud OAuth values'

					const resetButton = wrapper.find(selectors.resetNcOAuthFormButton)
					resetButton.vm.$emit('click')
					await flushPromises()

					expect(confirmSpy).toBeCalledTimes(1)
					expect(confirmSpy).toBeCalledWith(
						expectedConfirmText,
						expectedConfirmTitle,
						expectedConfirmOpts,
						expect.any(Function),
						true,
					)
					wrapper.destroy()
				})
				it('should create new client on confirm', async () => {
					jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({
							data: {
								clientId: 'new-client-id77',
								clientSecret: 'new-client-secret77',
							},
						}))
					const wrapper = getMountedWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'op-client-id',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: 'nc-client-id',
								nextcloud_client_secret: 'nc-client-secret',
							},
						},
					})
					await wrapper.vm.createNCOAuthClient()
					expect(wrapper.vm.state.nc_oauth_client).toMatchObject({
						clientId: 'new-client-id77',
						clientSecret: 'new-client-secret77',
					})
					expect(wrapper.vm.formMode.ncOauth).toBe(F_MODES.EDIT)
					expect(wrapper.vm.isFormCompleted.ncOauth).toBe(false)
					wrapper.destroy()
				})
			})
		})
		describe('recreate button', () => {
			it('should be displayed if nextcloud oauth credentials is empty and everything is set', async () => {
				const wrapper = getMountedWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: 'op-client-id',
						openproject_client_secret: 'op-client-secret',
						nc_oauth_client: null,
					},
					formMode: {
						projectFolderSetUp: F_MODES.VIEW,
					},
					showDefaultManagedProjectFolders: true,
					isFormCompleted: {
						projectFolderSetUp: true,
					},

				})
				const resetButton = wrapper.find(selectors.resetNcOAuthFormButton)
				expect(resetButton.isVisible()).toBe(true)
				expect(resetButton.text()).toBe('Create Nextcloud OAuth values')
				wrapper.destroy()
			})
		})
		describe('edit mode', () => {
			it('should show the form and hide the field values', async () => {
				const wrapper = getWrapper({
					state: {
						openproject_instance_url: 'http://openproject.com',
						authorization_method: AUTH_METHOD.OAUTH2,
						openproject_client_id: 'op-client-id',
						openproject_client_secret: 'op-client-secret',
						nc_oauth_client: {
							nextcloud_client_id: 'nc-client-id',
							nextcloud_client_secret: 'nc-client-secret',
						},
					},
				})
				await wrapper.setData({
					formMode: {
						ncOauth: F_MODES.EDIT,
					},
				})
				expect(wrapper.find(selectors.ncOauthForm)).toMatchSnapshot()
			})
			describe('done button', () => {
				it('should set the form to view mode if the oauth values are complete', async () => {
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
						},
					})
					await wrapper.setData({
						formMode: {
							ncOauth: F_MODES.EDIT,
						},
					})
					await wrapper.find(selectors.ncOauthForm)
						.find(selectors.submitNcOAuthFormButton)
						.trigger('click')
					expect(wrapper.vm.formMode.ncOauth).toBe(F_MODES.VIEW)
					expect(wrapper.vm.isFormCompleted.ncOauth).toBe(true)
				})
			})
		})
	})

	describe('Project folders form (Project Folder Setup)', () => {
		describe('Disable mode', () => {
			it('should not show groupfolders error note during initial setup if app is disabled', async () => {
				const wrapper = getWrapper({
					state: {
						openproject_instance_url: null,
						authorization_method: null,
						openproject_client_id: null,
						openproject_client_secret: null,
						nc_oauth_client: null,
						authorization_settings: {
							oidc_provider: null,
							targeted_audience_client_id: null,
							sso_provider_type: null,
						},
						fresh_project_folder_setup: true,
						project_folder_info: {
							status: false,
						},
						app_password_set: false,
						apps: {
							...appState.apps,
							groupfolders: {
								enabled: false,
								supported: false,
								minimum_version: '1.0.0',
							},
						},
						form: {
							serverHost: { complete: false },
							authenticationMethod: { complete: false },
						},
						formMode: {
							// Initial setup - project folder form is in DISABLE mode
							projectFolderSetUp: F_MODES.DISABLE,
						},
					},
				},
				)

				expect(wrapper.vm.formMode.projectFolderSetUp).toBe(F_MODES.DISABLE)
				expect(wrapper.vm.showGroupfoldersAppError).toBe(false)
				expect(wrapper.find(selectors.projectFolderErrorNote).exists()).toBe(false)
			})
		})
		describe('view mode', () => {
			describe('without project folder setup', () => {
				it('should show status as "Inactive"', () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: true,
							// project folder is already not set up
							project_folder_info: {
								status: false,
							},
							app_password_set: false,
							...appState,
						},
					})
					const projectFolderStatus = wrapper.find(selectors.projectFolderStatus)
					const actualProjectFolderStatusValue = projectFolderStatus.text()
					expect(actualProjectFolderStatusValue).toContain('Inactive')
					expect(wrapper.find(selectors.projectFolderErrorNote).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderSetupForm)).toMatchSnapshot()
				})
			})

			describe('disabled groupfolders app', function() {
				it('should not show error message if project folder is not setup', async () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: true,
							app_password_set: false,
							apps: {
								...appState.apps,
								groupfolders: {
									enabled: false,
									supported: false,
								},
							},
						},
						formMode: {
							projectFolderSetUp: F_MODES.VIEW,
						},
					})
					const formHeading = wrapper.find(selectors.projectFolderFormHeading)
					const errorNote = wrapper.find(selectors.projectFolderErrorNote)
					const editButton = wrapper.find(selectors.editProjectFolderSetup)
					expect(formHeading.exists()).toBe(true)
					expect(formHeading.attributes().haserror).toBe(undefined)
					expect(errorNote.exists()).toBe(false)
					expect(editButton.attributes().disabled).toBe(undefined)
				})

				it('should show error message if project folder is setup', async () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: false,
							project_folder_info: {
								status: true,
							},
							app_password_set: true,
							encryption_info: {
								server_side_encryption_enabled: false,
								encryption_enabled_for_groupfolders: false,
							},
							apps: {
								...appState.apps,
								groupfolders: {
									enabled: false,
									supported: false,
								},
							},
						},
						formMode: {
							projectFolderSetUp: F_MODES.VIEW,
						},
					})
					const formHeading = wrapper.find(selectors.projectFolderFormHeading)
					const errorNote = wrapper.find(selectors.projectFolderErrorNote)
					const editButton = wrapper.find(selectors.editProjectFolderSetup)
					expect(formHeading.exists()).toBe(true)
					expect(formHeading.attributes().haserror).toBe('true')
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
					expect(errorNote.attributes().errorlink).toBe(appLinks.groupfolders.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
					expect(editButton.attributes().disabled).toBe(undefined)
				})
			})
		})

		describe('edit mode ', () => {
			describe('fresh setup/after reset', () => {
				beforeEach(async () => {
					axios.put.mockReset()
					axios.get.mockReset()
				})

				it('should show the switch as "On" and button text label as "Setup OpenProject user, group and folder"', async () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: true,
							// project folder is already not set up
							project_folder_info: {
								status: false,
							},
							app_password_set: false,
							...appState,
						},
					})
					await wrapper.vm.setNCOAuthFormToViewMode()
					expect(wrapper.vm.isProjectFolderSwitchEnabled).toBe(true)
					const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
					expect(completeProjectFolderSetupWithGroupFolderButton.text()).toBe('Setup OpenProject user, group and folder')
				})

				it('should show button text label "Complete without team folder setup" when the switch is "off"', async () => {
					const wrapper = getWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: true,
							// team folder is already not set up
							project_folder_info: {
								status: false,
							},
							app_password_set: false,
							...appState,
						},
					})
					await wrapper.vm.setNCOAuthFormToViewMode()
					expect(wrapper.vm.isProjectFolderSwitchEnabled).toBe(true)
					const projectFolderSetupSwitchButton = wrapper.find(selectors.projectFolderSetupButtonStub)
					projectFolderSetupSwitchButton.vm.$emit('update:checked', false)
					await flushPromises()
					expect(wrapper.vm.isProjectFolderSwitchEnabled).toBe(false)
					const completeWithoutProjectFolderSetupButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
					expect(completeWithoutProjectFolderSetupButton.text()).toBe('Complete without project folders')
				})

				describe('on trigger "Complete without team folder setup"', () => {
					let wrapper = {}
					let saveOPOptionsSpy
					beforeEach(async () => {
						axios.put.mockReset()
						axios.get.mockReset()
						saveOPOptionsSpy = jest.spyOn(axios, 'put')
							.mockImplementationOnce(() => Promise.resolve({
								data: {
									oPUserAppPassword: null,
								},
							}))
						wrapper = getMountedWrapper({
							state: {
								openproject_instance_url: 'http://openproject.com',
								authorization_method: AUTH_METHOD.OAUTH2,
								openproject_client_id: 'some-client-id-here',
								openproject_client_secret: 'some-client-secret-here',
								default_enable_unified_search: false,
								default_enable_navigation: false,
								nc_oauth_client: {
									nextcloud_client_id: 'some-nc-client-id-here',
									nextcloud_client_secret: 'some-nc-client-secret-here',
								},
								fresh_project_folder_setup: true,
								// project folder is already not set up
								project_folder_info: {
									status: false,
								},
								app_password_set: false,
							},
						})
						await wrapper.setData({
							formMode: {
								projectFolderSetUp: F_MODES.EDIT,
							},
						})
						expect(wrapper.vm.formMode.projectFolderSetUp).toBe(F_MODES.EDIT)
						const projectFolderSetupSwitchButton = wrapper.find(selectors.projectFolderSetupSwitch)
						await projectFolderSetupSwitchButton.trigger('click')
						await wrapper.vm.$nextTick()
						const completeWithoutProjectFolderSetupButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
						expect(completeWithoutProjectFolderSetupButton.text()).toBe('Complete without project folders')
						await completeWithoutProjectFolderSetupButton.trigger('click')
						await wrapper.vm.$nextTick()
						expect(saveOPOptionsSpy).toBeCalledWith(
							'http://localhost/apps/integration_openproject/admin-config',
							{
								values: {
									setup_app_password: false,
									setup_project_folder: false,
								},
							},
						)
					})

					it('should set status "Inactive"', async () => {
						const projectFolderStatusWrapper = wrapper.find(selectors.projectFolderStatus)
						const actualProjectFolderStatusValue = projectFolderStatusWrapper.text()
						expect(actualProjectFolderStatusValue).toContain('Inactive')
					})

					it('should set form mode to view', async () => {
						expect(wrapper.vm.formMode.projectFolderSetUp).toBe(F_MODES.VIEW)

					})

					it('should not create user app password', async () => {
						expect(wrapper.vm.$data.oPUserAppPassword).toBe(null)
					})
				})

				// test for error while setting up the team folder
				describe('trigger on "Setup OpenProject user, group and folder" button', () => {
					beforeEach(async () => {
						axios.put.mockReset()
						axios.get.mockReset()
					})

					describe('upon failure', () => {
						it.each([
							[
								'should set the user already exists error message and error details when user already exists',
								{
									error: 'The user "OpenProject" already exists',
									expectedErrorDetailsMessage: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],
							[
								'should set the team folder name already exists error message and error details when team folder already exists',
								{
									error: 'The team folder name "OpenProject" already exists',
									expectedErrorDetailsMessage: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],
							[
								'should set the group already exists error message and error details when group already exists',
								{
									error: 'The group "OpenProject" already exists',
									expectedErrorDetailsMessage: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],

						])('%s', async (name, expectedErrorDetails) => {
							const wrapper = getWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: 'some-client-id-here',
									openproject_client_secret: 'some-client-secret-here',
									default_enable_unified_search: false,
									default_enable_navigation: false,
									nc_oauth_client: {
										nextcloud_client_id: 'some-nc-client-id-here',
										nextcloud_client_secret: 'some-nc-client-secret-here',
									},
									fresh_project_folder_setup: true,
									project_folder_info: {
										status: false,
									},
									app_password_set: false,
									projectFolderSetupError: null,
									...appState,
								},
							})

							await wrapper.setData({
								formMode: {
									projectFolderSetUp: F_MODES.EDIT,
								},
							})
							const getgroupfolderStatus = jest.spyOn(axios, 'get').mockImplementationOnce(() => Promise.resolve({
								data: {
									result: false,
								},
							}))

							// creating an error since the put request is not resolved
							const err = new Error()
							err.response = {}
							err.response.data = {}
							err.response.data.error = expectedErrorDetails.error

							const saveOPOptionsSpy = jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.reject(err))
							const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
							completeProjectFolderSetupWithGroupFolderButton.vm.$emit('click')
							await flushPromises()
							expect(getgroupfolderStatus).toBeCalledTimes(1)
							expect(saveOPOptionsSpy).toBeCalledWith(
								'http://localhost/apps/integration_openproject/admin-config',
								{
									values: {
										setup_app_password: true,
										setup_project_folder: true,
									},
								},
							)
							const projectFolderErrorMessage = wrapper.find(selectors.projectFolderErrorMessage)
							const projectFolderErrorMessageDetails = wrapper.find(selectors.projectFolderErrorMessageDetails)
							expect(projectFolderErrorMessage.text()).toBe(expectedErrorDetails.error)
							expect(projectFolderErrorMessageDetails.text()).toBe(expectedErrorDetails.expectedErrorDetailsMessage)
						})
					})

					describe('upon success', () => {
						let wrapper = {}
						let getgroupfolderStatusSpy
						let saveOPOptionsSpy
						beforeEach(async () => {
							axios.put.mockReset()
							axios.get.mockReset()
							wrapper = getMountedWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: 'some-client-id-here',
									openproject_client_secret: 'some-client-secret-here',
									nc_oauth_client: {
										nextcloud_client_id: 'some-nc-client-id-here',
										nextcloud_client_secret: 'some-nc-client-secret-here',
									},
									fresh_project_folder_setup: true,
									app_password_set: false,
									encryption_info: {
										server_side_encryption_enabled: false,
										encryption_enabled_for_groupfolders: false,
									},
								},
								isGroupFolderAlreadySetup: null,
							})
							await wrapper.setData({
								formMode: {
									projectFolderSetUp: F_MODES.EDIT,
								},
							})

							getgroupfolderStatusSpy = jest.spyOn(axios, 'get').mockImplementationOnce(() => Promise.resolve({
								data: {
									result: false,
								},
							}))
							saveOPOptionsSpy = jest.spyOn(axios, 'put')
								.mockImplementationOnce(() => Promise.resolve({
									data: {
										oPUserAppPassword: 'opUserAppPassword',
									},
								}))
							const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
							await completeProjectFolderSetupWithGroupFolderButton.trigger('click')
							await wrapper.vm.$nextTick()
							expect(getgroupfolderStatusSpy).toBeCalledTimes(1)
							expect(saveOPOptionsSpy).toBeCalledWith(
								'http://localhost/apps/integration_openproject/admin-config',
								{
									values: {
										setup_app_password: true,
										setup_project_folder: true,
									},
								},
							)
							await wrapper.vm.$nextTick()
						})

						it('should set status as "Active"', async () => {
							expect(wrapper.vm.$data.oPUserAppPassword).toBe('opUserAppPassword')
							expect(wrapper.vm.formMode.opUserAppPassword).toBe(F_MODES.EDIT)
							await wrapper.vm.$nextTick()
							const projectFolderStatus = wrapper.find(selectors.projectFolderStatus)
							const actualProjectFolderStatusValue = projectFolderStatus.text()
							expect(actualProjectFolderStatusValue).toContain('Active')
						})

						it('should set user app password form to edit mode', async () => {
							expect(wrapper.vm.formMode.opUserAppPassword).toBe(F_MODES.EDIT)
						})

						it('should set project folder setup form to edit mode', async () => {
							expect(wrapper.vm.formMode.projectFolderSetUp).toBe(F_MODES.VIEW)
						})
						it('should create a new app password', async () => {
							expect(wrapper.vm.$data.oPUserAppPassword).toBe('opUserAppPassword')
						})
					})
				})
			})

			describe('deactivate', function() {
				describe('after complete setup', () => {
					let wrapper = {}
					let saveOPOptionsSpy
					beforeEach(async () => {
						wrapper = getMountedWrapper({
							state: {
								openproject_instance_url: 'http://openproject.com',
								authorization_method: AUTH_METHOD.OAUTH2,
								openproject_client_id: 'some-client-id-here',
								openproject_client_secret: 'some-client-secret-here',
								nc_oauth_client: {
									nextcloud_client_id: 'some-nc-client-id-here',
									nextcloud_client_secret: 'some-nc-client-secret-here',
								},
								fresh_project_folder_setup: false,
								project_folder_info: {
									status: true,
								},
								app_password_set: true,
								encryption_info: {
									server_side_encryption_enabled: false,
									encryption_enabled_for_groupfolders: false,
								},
							},
						})
						await wrapper.setData({
							formMode: {
								projectFolderSetUp: F_MODES.EDIT,
							},
							oPUserAppPassword: 'userAppPassword',
						})
						saveOPOptionsSpy = jest.spyOn(axios, 'put')
							.mockImplementationOnce(() => Promise.resolve({
								data: {
									oPUserAppPassword: null,
								},
							}))
						const projectFolderSetupSwitchButton = wrapper.find(selectors.projectFolderSetupSwitch)
						await projectFolderSetupSwitchButton.trigger('click')
						await wrapper.vm.$nextTick()
						const completeWithoutProjectFolderSetupButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
						await completeWithoutProjectFolderSetupButton.trigger('click')
						await wrapper.vm.$nextTick()
					})

					it('should delete user app password', async () => {
						expect(saveOPOptionsSpy).toBeCalledWith(
							'http://localhost/apps/integration_openproject/admin-config',
							{
								values: {
									setup_app_password: false,
									setup_project_folder: false,
								},
							},
						)
						expect(wrapper.vm.state.app_password_set).toBe(false)
						expect(wrapper.vm.state.oPUserAppPassword).not.toBe('userAppPassword')
						expect(wrapper.vm.state.oPUserAppPassword).not.toBe(null)
					})

					it('should set project folder status to "Inactive"', async () => {
						expect(saveOPOptionsSpy).toBeCalledWith(
							'http://localhost/apps/integration_openproject/admin-config',
							{
								values: {
									setup_app_password: false,
									setup_project_folder: false,
								},
							},
						)
						const projectFolderStatus = wrapper.find(selectors.projectFolderStatus)
						const actualProjectFolderStatusValue = projectFolderStatus.text()
						expect(actualProjectFolderStatusValue).toContain('Inactive')
					})
				})
			})

			describe('default deactivate state', function() {
				let wrapper = {}
				beforeEach(async () => {
					wrapper = getMountedWrapper({
						state: {
							openproject_instance_url: 'http://openproject.com',
							authorization_method: AUTH_METHOD.OAUTH2,
							openproject_client_id: 'some-client-id-here',
							openproject_client_secret: 'some-client-secret-here',
							nc_oauth_client: {
								nextcloud_client_id: 'some-nc-client-id-here',
								nextcloud_client_secret: 'some-nc-client-secret-here',
							},
							fresh_project_folder_setup: false,
							// team folder is already not set up
							project_folder_info: {
								status: false,
							},
							app_password_set: false,
						},
					})
					await wrapper.setData({
						formMode: {
							projectFolderSetUp: F_MODES.EDIT,
						},
					})
				})

				it('should show project folder status as "Inactive"', async () => {
					await wrapper.setData({
						formMode: {
							projectFolderSetUp: F_MODES.VIEW,
						},
					})
					const projectFolderStatus = wrapper.find(selectors.projectFolderStatus)
					const actualProjectFolderStatusValue = projectFolderStatus.text()
					expect(actualProjectFolderStatusValue).toContain('Inactive')
				})

				it('should set the button label to "keep current change"', async () => {
					const completeWithoutProjectFolderSetupButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
					expect(completeWithoutProjectFolderSetupButton.text()).toBe('Keep current setup')
				})

				it('should show button label to "Setup OpenProject user, group and folder" when switch is "On"', async () => {
					const projectFolderSetupSwitchButton = wrapper.find(selectors.projectFolderSetupSwitch)
					await projectFolderSetupSwitchButton.trigger('click')
					await wrapper.vm.$nextTick()
					expect(wrapper.vm.isProjectFolderSwitchEnabled).toBe(true)
					const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
					expect(completeProjectFolderSetupWithGroupFolderButton.text()).toBe('Setup OpenProject user, group and folder')
				})
			})

			describe('complete setup (project folder and app password)', function() {
				describe('edit mode', function() {
					let wrapper = {}
					let getgroupfolderStatusSpy
					beforeEach(async () => {
						axios.put.mockReset()
						axios.get.mockReset()
						wrapper = getMountedWrapper({
							state: {
								openproject_instance_url: 'http://openproject.com',
								authorization_method: AUTH_METHOD.OAUTH2,
								openproject_client_id: 'some-client-id-here',
								openproject_client_secret: 'some-client-secret-here',
								nc_oauth_client: {
									nextcloud_client_id: 'some-nc-client-id-here',
									nextcloud_client_secret: 'some-nc-client-secret-here',
								},
								fresh_project_folder_setup: false,
								project_folder_info: {
									status: true,
								},
								app_password_set: true,
								encryption_info: {
									server_side_encryption_enabled: false,
									encryption_enabled_for_groupfolders: false,
								},
							},
							isGroupFolderAlreadySetup: null,
						})
						await wrapper.setData({
							formMode: {
								projectFolderSetUp: F_MODES.EDIT,
							},
							oPUserAppPassword: 'opUserPassword',
						})
						getgroupfolderStatusSpy = jest.spyOn(axios, 'get').mockImplementationOnce(() => Promise.resolve({
							data: {
								result: true,
							},
						}))
					})

					it('should show button label to "keep current"', async () => {
						const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
						expect(completeProjectFolderSetupWithGroupFolderButton.text()).toBe('Keep current setup')
					})

					it('should not create new user app password on trigger "Keep on current change"', async () => {
						const completeProjectFolderSetupWithGroupFolderButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
						expect(completeProjectFolderSetupWithGroupFolderButton.text()).toBe('Keep current setup')
						completeProjectFolderSetupWithGroupFolderButton.trigger('click')
						expect(getgroupfolderStatusSpy).toBeCalledTimes(1)
						expect(wrapper.vm.oPUserAppPassword).toBe('opUserPassword')
					})

					it('should show button label as "Complete without team folder setup" when switch is "off" ', async () => {
						const projectFolderSetupSwitchButton = wrapper.find(selectors.projectFolderSetupSwitch)
						await projectFolderSetupSwitchButton.trigger('click')
						const completeWithoutProjectFolderSetupButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
						expect(completeWithoutProjectFolderSetupButton.text()).toBe('Complete without project folders')
					})

					it('should set switch as "on" again (same as fresh set up) when completely reset', async () => {
						const wrapper = getMountedWrapper({
							state: {
								openproject_instance_url: null,
								authorization_method: null,
								openproject_client_id: null,
								openproject_client_secret: null,
								nc_oauth_client: null,
								fresh_project_folder_setup: true,
								project_folder_info: {
									status: true,
								},
								app_password_set: false,
							},
						})
						expect(wrapper.vm.isProjectFolderSwitchEnabled).toBe(true)
					})

					describe('disabled groupfolders app', function() {
						beforeEach(async () => {
							wrapper = getWrapper({
								state: {
									openproject_instance_url: 'http://openproject.com',
									authorization_method: AUTH_METHOD.OAUTH2,
									openproject_client_id: 'some-client-id-here',
									openproject_client_secret: 'some-client-secret-here',
									nc_oauth_client: {
										nextcloud_client_id: 'some-nc-client-id-here',
										nextcloud_client_secret: 'some-nc-client-secret-here',
									},
									fresh_project_folder_setup: false,
									project_folder_info: {
										status: true,
									},
									app_password_set: true,
									encryption_info: {
										server_side_encryption_enabled: false,
										encryption_enabled_for_groupfolders: false,
									},
									apps: {
										...appState.apps,
										groupfolders: {
											enabled: false,
											supported: false,
										},
									},
								},
								isGroupFolderAlreadySetup: null,
							})
							await wrapper.find(selectors.editProjectFolderSetup).vm.$emit('click')
						})
						it('should show error message', async () => {
							const formHeading = wrapper.find(selectors.projectFolderFormHeading)
							const errorNote = wrapper.find(selectors.projectFolderErrorNote)
							const saveButton = wrapper.find(selectors.completeProjectFolderSetupWithGroupFolderButton)
							expect(formHeading.exists()).toBe(true)
							expect(formHeading.attributes().haserror).toBe('true')
							expect(errorNote.exists()).toBe(true)
							expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
							expect(errorNote.attributes().errorlink).toBe(appLinks.groupfolders.installLink)
							expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
							expect(saveButton.attributes().disabled).toBe('true')
							expect(saveButton.text()).toBe('Keep current setup')
						})
						it('should be able to disable the project folder setup and no error card', async () => {
							const toggleButton = wrapper.find(selectors.projectFolderSetupButtonStub)
							expect(toggleButton.attributes().checked).toBe('true')

							await toggleButton.vm.$emit('update:checked', false)

							const saveButton = wrapper.find(selectors.completeWithoutProjectFolderSetupButton)
							expect(toggleButton.attributes().checked).toBe(undefined)
							expect(saveButton.text()).toBe('Complete without project folders')

							const formHeading = wrapper.find(selectors.projectFolderFormHeading)
							const errorNote = wrapper.find(selectors.projectFolderErrorNote)
							expect(formHeading.attributes().haserror).toBe(undefined)
							expect(errorNote.exists()).toBe(false)
						})
					})
				})
			})
		})
	})

	describe('user app password reset', () => {
		let confirmSpy
		let wrapper
		beforeEach(async () => {
			axios.put.mockReset()
			confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
			wrapper = getMountedWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-here',
					openproject_client_secret: 'some-client-secret-here',
					nc_oauth_client: {
						nextcloud_client_id: 'some-nc-client-id-here',
						nextcloud_client_secret: 'some-nc-client-secret-here',
					},
					app_password_set: true,
				},
			})
			await wrapper.setData({
				oPUserAppPassword: 'oldUserAppPassword',
			})
		})
		afterEach(() => {
			jest.clearAllMocks()
		})
		it('should trigger a confirm dialog', async () => {
			const expectedConfirmText = 'If you proceed, your old application password for the OpenProject user will be deleted and you will receive a new OpenProject user password.'
			const expectedConfirmOpts = {
				cancel: 'Cancel',
				confirm: 'Yes, replace',
				confirmClasses: 'error',
				type: 70,
			}
			const expectedConfirmTitle = 'Replace user app password'
			const resetUserAppPassword = wrapper.find(selectors.userAppPasswordButton)
			await resetUserAppPassword.trigger('click')
			await wrapper.vm.$nextTick()
			expect(confirmSpy).toBeCalledTimes(1)
			expect(confirmSpy).toBeCalledWith(
				expectedConfirmText,
				expectedConfirmTitle,
				expectedConfirmOpts,
				expect.any(Function),
				true,
			)
			wrapper.destroy()
		})

		it('should replace old password with new password on confirm', async () => {
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(() => Promise.resolve({
					data: {
						oPUserAppPassword: 'newUserAppPassword',
					},
				}))
			await wrapper.vm.createNewAppPassword()
			expect(saveOPOptionsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/admin-config',
				{
					values: {
						setup_app_password: true,
					},
				},
			)
			expect(wrapper.vm.oPUserAppPassword).toBe('newUserAppPassword')
			expect(wrapper.vm.oPUserAppPassword).not.toBe('oldUserAppPassword')
			expect(wrapper.vm.formMode.opUserAppPassword).toBe(F_MODES.EDIT)
			expect(wrapper.vm.isFormCompleted.opUserAppPassword).toBe(false)
		})
	})

	describe('error after project folder is already setup', () => {
		beforeEach(async () => {
			axios.put.mockReset()
			axios.get.mockReset()
		})
		it.each([
			[
				'should set the user already exists error message and error details when user already exists',
				{
					error: 'The user "OpenProject" already exists',
					expectedErrorDetailsMessage: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
				},
			],
		])('%s', async (name, expectedErrorDetails) => {
			const wrapper = getWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-here',
					openproject_client_secret: 'some-client-secret-here',
					default_enable_unified_search: false,
					default_enable_navigation: false,
					nc_oauth_client: {
						nextcloud_client_id: 'some-nc-client-id-here',
						nextcloud_client_secret: 'some-nc-client-secret-here',
					},
					// status is false
					// with error message when something went wrong after project folder is already setup
					project_folder_info: {
						errorMessage: expectedErrorDetails.error,
						status: false,
					},
					app_password_set: true,
				},
			})
			await wrapper.setData({
				formMode: {
					projectFolderSetUp: F_MODES.EDIT,
				},
				projectFolderSetupError: expectedErrorDetails.error,
			})
			expect(wrapper.vm.isFormCompleted.opUserAppPassword).toBe(true)
			const projectFolderErrorMessage = wrapper.find(selectors.projectFolderErrorMessage)
			const projectFolderErrorMessageDetails = wrapper.find(selectors.projectFolderErrorMessageDetails)
			expect(projectFolderErrorMessage.text()).toBe(expectedErrorDetails.error)
			expect(projectFolderErrorMessageDetails.text()).toBe(expectedErrorDetails.expectedErrorDetailsMessage)
		})
	})

	describe('Encryption warning after project folder setup', () => {
		beforeEach(async () => {
			axios.put.mockReset()
			axios.get.mockReset()
		})

		it.each([
			[
				'should show warning when server side encryption is enabled but encryption for groupfolders is not enabled',
				{
					server_side_encryption_enabled: true,
					encryption_enabled_for_groupfolders: false,
				},
				true,
			],
			[
				'should not show warning when server side encryption and groupfolders encryption is enabled',
				{
					server_side_encryption_enabled: true,
					encryption_enabled_for_groupfolders: true,
				},
				false,
			],
			[
				'should not show warning when server side encryption not enabled but groupfolders encryption is enabled',
				{
					server_side_encryption_enabled: false,
					encryption_enabled_for_groupfolders: true,
				},
				false,
			],
		])('%s', (name, encryptionInfoState, expectedResult) => {
			const wrapper = getWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-here',
					openproject_client_secret: 'some-client-secret-here',
					default_enable_unified_search: false,
					default_enable_navigation: false,
					nc_oauth_client: {
						nextcloud_client_id: 'some-nc-client-id-here',
						nextcloud_client_secret: 'some-nc-client-secret-here',
					},
					project_folder_info: {
						status: true,
					},
					app_password_set: true,
					encryption_info: encryptionInfoState,
				},
			})
			const encryptionWarningNoteCard = wrapper.find(selectors.encryptionNoteCardWarningSelector)
			expect(encryptionWarningNoteCard.exists()).toBe(expectedResult)
			if (expectedResult) {
				expect(encryptionWarningNoteCard.attributes().type).toBe('warning')
				expect(encryptionWarningNoteCard.find('p.note-card--title').text()).toBe('Encryption for the Team Folders App is not enabled.')
			}
		})
	})

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
				await wrapper.vm.resetAllAppValues()

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
							setup_project_folder: false,
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
			it('should reset all settings on confirm along with app password when app password is set', async () => {
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
						app_password_set: true,
					},
					oPUserAppPassword: 'oPUserAppPassword',
				})

				const saveOPOptionsSpy = jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({ data: true }))
				await wrapper.vm.resetAllAppValues()

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
							setup_project_folder: false,
							setup_app_password: false,
							oidc_provider: null,
							sso_provider_type: null,
							targeted_audience_client_id: null,
							token_exchange: null,
						},
					},
				)
				// no new app password is received on response
				expect(wrapper.vm.oPUserAppPassword).toBe(null)
				expect(wrapper.vm.oPUserAppPassword).not.toBe('oPUserAppPassword')
				axios.put.mockReset()
			})
			it('should reload the window at the end', async () => {
				await wrapper.vm.resetAllAppValues()
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
			})
			expect(wrapper.find(selectors.defaultUserConfigurationsForm)).toMatchSnapshot()
		})
		it('should not be visible if the integration is not complete', () => {
			const wrapper = getMountedWrapper({
				state: {
					openproject_instance_url: 'http://openproject.com',
					authorization_method: AUTH_METHOD.OAUTH2,
					openproject_client_id: 'some-client-id-for-op',
					openproject_client_secret: 'some-client-secret-for-op',
					nc_oauth_client: null,
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
				},
				form: {
					serverHost: { complete: true },
					authenticationMethod: { complete: true },
				},
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
				},
				form: {
					serverHost: { complete: true },
					authenticationMethod: { complete: true },
				},
			})
			const $defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			await $defaultEnableNavigation.trigger('click')
			await localVue.nextTick()

			expect(dialogs.showError).toBeCalledTimes(1)
			expect(dialogs.showError).toBeCalledWith('Failed to save default user configuration: Some message')

		})
	})

	describe('revoke OpenProject OAuth token', () => {
		beforeEach(() => {
			axios.put.mockReset()
			dialogs.showSuccess.mockReset()
			dialogs.showError.mockReset()
		})
		it('should show success when revoke status is success', async () => {
			dialogs.showSuccess
				.mockImplementationOnce()
				.mockImplementationOnce()
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(
					() => Promise.resolve({ data: { status: true, oPOAuthTokenRevokeStatus: 'success' } }),
				)
			const wrapper = getMountedWrapper({
				state: completeOAUTH2IntegrationState,
			})
			await wrapper.vm.saveOPOptions()

			await localVue.nextTick()

			expect(saveOPOptionsSpy).toBeCalledTimes(1)
			expect(dialogs.showSuccess).toBeCalledTimes(2)
			expect(dialogs.showSuccess).toBeCalledWith('OpenProject admin options saved')
			expect(dialogs.showSuccess).toBeCalledWith('Successfully revoked users\' OpenProject OAuth access tokens')

		})
		it.each([
			['connection_error', 'Failed to perform revoke request due to connection error with the OpenProject server'],
			['other_error', 'Failed to revoke some users\' OpenProject OAuth access tokens'],
		])('should show error message on various failure', async (errorCode, errorMessage) => {
			dialogs.showSuccess
				.mockImplementationOnce()
				.mockImplementationOnce()
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(
					() => Promise.resolve({ data: { status: true, oPOAuthTokenRevokeStatus: errorCode } }),
				)
			const wrapper = getMountedWrapper({
				state: completeOAUTH2IntegrationState,
			})
			await wrapper.vm.saveOPOptions()

			await localVue.nextTick()

			expect(saveOPOptionsSpy).toBeCalledTimes(1)
			expect(dialogs.showSuccess).toBeCalledTimes(1)
			expect(dialogs.showError).toBeCalledTimes(1)
			expect(dialogs.showSuccess).toBeCalledWith('OpenProject admin options saved')
			expect(dialogs.showError).toBeCalledWith(errorMessage)

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
