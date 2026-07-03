/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // eslint-disable-line n/no-unpublished-import
import FormProjectFolder from '../../../../src/components/admin/FormProjectFolder.vue'
import { F_MODES, AUTH_METHOD, ADMIN_SETTINGS_FORM } from '../../../../src/utils.js'
import { appLinks } from '../../../../src/constants/links.js'
import { messagesFmt, messages } from '../../../../src/constants/messages.js'

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
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))
jest.mock('../../../../src/api/settings.js', () => ({
	saveAdminConfig: jest.fn(() => ''),
}))

global.t = (app, text) => text
global.OC = {
	dialogs: {
		confirmDestructive: jest.fn(),
		YES_NO_BUTTONS: 70,
	},
}
const writeText = jest.fn()
Object.assign(global.navigator, {
	clipboard: {
		writeText,
	},
})

const localVue = createLocalVue()

const selectors = {
	errorNote: 'errornote-stub',
	noteCard: 'ncnotecard-stub',
	projectFolderFormHeading: '.project-folder-setup formheading-stub',
	projectFolderFormContainer: '.project-folder-form-container',
	projectFolderFormStatus: '.project-folder-status',
	projectFolderFormStatusLabel: '.project-folder-status-value',
	projectFolderForm: '.project-folder-form',
	projectFolderSubmitButton: '.project-folder-form-container ncbutton-stub',
	appPasswordFormContainer: '.app-password-form-container',
	appPasswordFormHeading: '.app-password-form-container formheading-stub',
	appPasswordSubmitButton: 'ncbutton-stub[data-test-id="submit-op-system-password-form-btn"]',
	appPasswordResetButton: 'ncbutton-stub[data-test-id="reset-user-app-password"]',
}

const formState = structuredClone(ADMIN_SETTINGS_FORM)
formState.serverHost.complete = true
formState.authenticationMethod.value = AUTH_METHOD.OAUTH2
formState.authenticationMethod.complete = true
formState.openprojectOauth.complete = true
formState.nextcloudOauth.complete = true

const defaultProps = {
	formState,
	formOrder: 5,
	projectFolderInfo: {
		projectFolderEnabled: true,
		hasAppPassword: false,
		app: {
			enabled: true,
			supported: true,
			minimum_version: '1.0.0',
			name: 'Team folders',
		},
		folderStatus: {
			status: false,
			error: null,
		},
		encryption: {
			server_side_encryption_enabled: false,
			encryption_enabled_for_groupfolders: false,
		},
	},
}
describe('Component: FormProjectFolder', () => {
	afterEach(() => {
		jest.restoreAllMocks()
	})

	describe('Project folder setup', () => {
		describe('disable mode', () => {
			it('should not show form fields', async () => {
				const props = structuredClone(defaultProps)
				props.formState.nextcloudOauth.complete = false
				const wrapper = getWrapper({ props })

				expect(wrapper.vm.folderFormMode).toBe(F_MODES.DISABLE)
				const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
				expect(projectFolderFormHeading.attributes().isdisabled).toBe('true')
				expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
				expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)
				expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
				expect(projectFolderFormHeading.attributes().index).toBe(defaultProps.formOrder.toString())
				expect(wrapper.find(selectors.projectFolderFormContainer).exists()).toBe(false)
				expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
				expect(wrapper.element).toMatchSnapshot()
			})
		})
		describe('view mode', () => {
			describe('project folder setup status', () => {
				it('should show active if the project folder is enabled', () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						projectFolderEnabled: true,
						hasAppPassword: true,
						folderStatus: {
							status: true,
						},
					}
					const wrapper = getWrapper({ props })

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.emitted().formcomplete.length).toBe(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
					expect(projectFolderFormHeading.attributes().index).toBe(defaultProps.formOrder.toString())

					expect(wrapper.find(selectors.projectFolderFormContainer).exists()).toBe(true)
					const projectFolderFormStatus = wrapper.find(selectors.projectFolderFormStatus)
					expect(projectFolderFormStatus.exists()).toBe(true)
					expect(projectFolderFormStatus.find(selectors.projectFolderFormStatusLabel).text()).toContain('Active')
					expect(wrapper.find(selectors.projectFolderSubmitButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)

					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)
					const appPasswordFormHeading = wrapper.find(selectors.appPasswordFormHeading)
					expect(appPasswordFormHeading.attributes().isdisabled).toBe(undefined)
					expect(appPasswordFormHeading.attributes().iscomplete).toBe('true')
					expect(appPasswordFormHeading.attributes().index).toBe((defaultProps.formOrder + 1).toString())
					expect(wrapper.find(selectors.appPasswordSubmitButton).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordResetButton).text()).toBe('Replace application password')

					expect(wrapper.element).toMatchSnapshot()
				})
				it('should show inactive if the project folder is disabled', () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						projectFolderEnabled: false,
						hasAppPassword: false,
					}
					const wrapper = getWrapper({ props })

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
					expect(wrapper.emitted().formcomplete.length).toBe(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)

					expect(wrapper.find(selectors.projectFolderFormContainer).exists()).toBe(true)
					const projectFolderFormStatus = wrapper.find(selectors.projectFolderFormStatus)
					expect(projectFolderFormStatus.exists()).toBe(true)
					expect(projectFolderFormStatus.find(selectors.projectFolderFormStatusLabel).text()).toContain('Inactive')
					expect(wrapper.find(selectors.projectFolderSubmitButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					expect(wrapper.element).toMatchSnapshot()
				})
				it('should show completed form even if the auth settings are incomplete', () => {
					const props = structuredClone(defaultProps)
					props.formState.nextcloudOauth.complete = false
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						projectFolderEnabled: false,
						hasAppPassword: false,
					}
					const wrapper = getWrapper({ props })

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
					expect(wrapper.emitted().formcomplete.length).toBe(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)

					expect(wrapper.find(selectors.projectFolderFormContainer).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderSubmitButton).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
			})

			describe('disabled groupfolders app', function() {
				it('should not show error message if project folder is disabled', async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						projectFolderEnabled: false,
						hasAppPassword: false,
						app: {
							enabled: false,
						},
					}
					const wrapper = getWrapper({ props })

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
					expect(wrapper.emitted().formcomplete.length).toBe(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)

					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderSubmitButton).attributes().disabled).toBe(undefined)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					expect(wrapper.element).toMatchSnapshot()
				})

				it('should show error message if project folder is enabled', async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						projectFolderEnabled: true,
						hasAppPassword: true,
						app: {
							enabled: false,
						},
					}
					const wrapper = getWrapper({ props })

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.emitted().formcomplete.length).toBe(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe('true')

					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					const errorNote = wrapper.find(selectors.errorNote)
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
					expect(errorNote.attributes().errorlink).toBe(appLinks.groupfolders.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
					expect(wrapper.find(selectors.projectFolderSubmitButton).attributes().disabled).toBe(undefined)

					expect(wrapper.element).toMatchSnapshot()
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
})

function getWrapper({ data = {}, props = {} } = {}) {
	return shallowMount(FormProjectFolder, {
		localVue,
		mocks: {
			t: (app, msg) => msg,
		},
		propsData: { ...defaultProps, ...props },
		data() {
			return data
		},
	})
}
