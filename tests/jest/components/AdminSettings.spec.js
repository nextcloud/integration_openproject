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

	const commonState = {
		form: {
			serverHost: { complete: true },
			authenticationMethod: {
				value: AUTH_METHOD.OAUTH2,
				complete: true,
			},
			openprojectOauth: { complete: true },
			nextcloudOauth: { complete: true },
		},
	}

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
							fresh_project_folder_setup: false,
							// project folder is already set up
							project_folder_info: {
								status: true,
							},
							app_password_set: false,
							...appState,
						},
						...commonState,
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
						...commonState,
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
							// project folder is not set up
							project_folder_info: {
								status: false,
							},
							app_password_set: false,
							...appState,
						},
						...commonState,
					})
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
						...commonState,
					})
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
							...commonState,
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
								...commonState,
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
								...commonState,
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
