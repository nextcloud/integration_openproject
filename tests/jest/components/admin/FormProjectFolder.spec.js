/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showSuccess, showError } from '@nextcloud/dialogs'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import flushPromises from 'flush-promises' // eslint-disable-line n/no-unpublished-import
import { toMatchSerializedSnapshot } from '../../utils.js'
import FormProjectFolder from '../../../../src/components/admin/FormProjectFolder.vue'
import { F_MODES, AUTH_METHOD, ADMIN_SETTINGS_FORM } from '../../../../src/utils.js'
import { appLinks } from '../../../../src/constants/links.js'
import { messagesFmt, messages } from '../../../../src/constants/messages.js'
import { saveAdminConfig, getProjectFolderStatus } from '../../../../src/api/settings.js'

jest.mock('@nextcloud/dialogs', () => ({
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))
jest.mock('../../../../src/api/settings.js', () => ({
	saveAdminConfig: jest.fn(() => ''),
	getProjectFolderStatus: jest.fn(Promise.resolve({ data: { result: false } })),
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
	noteCardWarning: 'ncnotecard-stub .note-card--warning-description',
	noteCardTitle: 'ncnotecard-stub .note-card--title',
	noteCardDescription: 'ncnotecard-stub .note-card--error-description',
	projectFolderFormHeading: '.project-folder-setup formheading-stub',
	projectFolderFormContainer: '.project-folder-form-container',
	projectFolderFormStatus: '.project-folder-status',
	projectFolderFormStatusLabel: '.project-folder-status-value',
	projectFolderForm: '.project-folder-form',
	projectFolderSetupSwitch: '.project-folder-form nccheckboxradioswitch-stub',
	projectFolderDescription: '.project-folder-description',
	projectFolderDisabledDescription: '.complete-without-groupfolders',
	projectFolderActionButton: '.project-folder-form-container ncbutton-stub',
	appPasswordFormContainer: '.app-password-form-container',
	appPasswordFormHeading: '.app-password-form-container formheading-stub',
	appPasswordFormLabel: '.app-password-form-container fieldvalue-stub',
	appPasswordInput: '.app-password-form-container textinput-stub',
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
		freshSetup: true,
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
	beforeEach(() => {
		getProjectFolderStatus.mockImplementation(() => Promise.resolve({ data: { result: false } }))
	})

	afterEach(() => {
		jest.clearAllMocks()
		saveAdminConfig.mockReset()
		getProjectFolderStatus.mockReset()
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
				toMatchSerializedSnapshot(wrapper.html())
			})
		})
		describe('view mode', () => {
			describe('project folder setup status', () => {
				it('should show active if the project folder is enabled', () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
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
					expect(projectFolderFormStatus.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)

					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)
					const appPasswordFormHeading = wrapper.find(selectors.appPasswordFormHeading)
					expect(appPasswordFormHeading.attributes().isdisabled).toBe(undefined)
					expect(appPasswordFormHeading.attributes().iscomplete).toBe('true')
					expect(appPasswordFormHeading.attributes().index).toBe((defaultProps.formOrder + 1).toString())
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordSubmitButton).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordResetButton).text()).toBe('Replace application password')

					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show inactive if the project folder is disabled', async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
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
					expect(projectFolderFormStatus.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Inactive')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show completed form even if the auth settings are incomplete', () => {
					const props = structuredClone(defaultProps)
					props.formState.nextcloudOauth.complete = false
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
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
					expect(wrapper.find(selectors.projectFolderActionButton).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
			})

			describe('teamfolders app error', function() {
				it('should not show error message if project folder is disabled', async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
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
					expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe(undefined)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show error message if project folder is enabled', async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
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
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					const errorNote = wrapper.find(selectors.errorNote)
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
					expect(errorNote.attributes().errorlink).toBe(appLinks.groupfolders.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
					expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe(undefined)

					toMatchSerializedSnapshot(wrapper.html())
				})
			})
		})

		describe('edit mode ', () => {
			describe('fresh setup', () => {
				let wrapper
				beforeEach(async () => {
					wrapper = getWrapper({ props: defaultProps })
				})

				it('should show enabled form fields', async () => {
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
					expect(wrapper.emitted().formcomplete).toBe(undefined)
					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
					expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
					expect(projectFolderFormHeading.attributes().haserror).toBe(undefined)

					const projectFolderForm = wrapper.find(selectors.projectFolderForm)
					expect(projectFolderForm.exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe('true')
					expect(wrapper.findAll(selectors.projectFolderDescription).length).toBe(2)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
					const projectFolderActionButton = projectFolderForm.find(selectors.projectFolderActionButton)
					expect(projectFolderActionButton.text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
					expect(wrapper.find(selectors.projectFolderDisabledDescription).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
				it('should show disabled form fields if project folder is disabled', async () => {
					const projectFolderSetupSwitch = wrapper.find(selectors.projectFolderSetupSwitch)
					projectFolderSetupSwitch.vm.$emit('update:checked', false)
					await flushPromises()

					expect(projectFolderSetupSwitch.attributes().checked).toBe(undefined)
					const projectFolderActionButton = wrapper.find(selectors.projectFolderActionButton)
					expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(projectFolderActionButton.text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})

				describe('on save: disabled project folder', () => {
					it('should set status "Inactive"', async () => {
						const spySetAppPasswordFormToEditMode = jest.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
						const projectFolderSetupSwitch = wrapper.find(selectors.projectFolderSetupSwitch)
						projectFolderSetupSwitch.vm.$emit('update:checked', false)
						await wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)
						expect(saveAdminConfig).toHaveBeenCalledWith({
							setup_app_password: false,
							setup_project_folder: false,
						})
						expect(spySetAppPasswordFormToEditMode).not.toHaveBeenCalled()
						expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
						expect(wrapper.vm.loading).toBe(false)
						expect(wrapper.emitted().formcomplete.length).toBe(1)
						expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')

						expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
						const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
						expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
						expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('true')
						expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Inactive')
						expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
						expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
						toMatchSerializedSnapshot(wrapper.html())
					})

					describe('enable project folder immediately after complete setup', () => {
						it('should show the correct label and form', async () => {
							const appPassword = '12345678'
							saveAdminConfig.mockImplementation(() => Promise.resolve({
								data: {
									oPUserAppPassword: appPassword,
								},
							}))
							const props = structuredClone(defaultProps)
							props.formState.projectFolder.complete = true
							const wrapper = getWrapper({ props })

							wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
							wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()
							// edit mode
							wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// enable project folder
							wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// disable project folder
							wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

							// enable project folder
							wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// save
							wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(saveAdminConfig).toHaveBeenCalledWith({
								setup_project_folder: true,
								setup_app_password: true,
							})
							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
							expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
							expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
							// save app password
							const appPasswordSubmitButton = wrapper.find(selectors.appPasswordSubmitButton)
							expect(appPasswordSubmitButton.text()).toBe('Done, complete setup')
							appPasswordSubmitButton.vm.$emit('click')
							await flushPromises()
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
						})
					})
				})

				describe('on save: enabled project folder', () => {
					describe('upon success', () => {
						beforeEach(() => {
							const props = structuredClone(defaultProps)
							props.formState.projectFolder.complete = true
							wrapper = getWrapper({ props })
						})

						it('should set status as "Active"', async () => {
							const appPassword = '12345678'
							saveAdminConfig.mockImplementation(() => Promise.resolve({
								data: {
									oPUserAppPassword: appPassword,
								},
							}))
							const spySetAppPasswordFormToEditMode = jest.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
							expect(wrapper.vm.appPassword).toBe(null)
							await wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(saveAdminConfig).toHaveBeenCalledTimes(1)
							expect(saveAdminConfig).toHaveBeenCalledWith({
								setup_app_password: true,
								setup_project_folder: true,
							})
							expect(spySetAppPasswordFormToEditMode).toHaveBeenCalledTimes(1)
							expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.vm.loading).toBe(false)
							expect(wrapper.vm.appPassword).toBe(appPassword)
							expect(wrapper.emitted().formcomplete.length).toBe(1)
							expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
							expect(showSuccess).toHaveBeenCalledTimes(1)
							expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')

							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
							expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')

							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(false)
							const appPasswordFormHeading = wrapper.find(selectors.appPasswordFormHeading)
							expect(appPasswordFormHeading.attributes().isdisabled).toBe(undefined)
							expect(appPasswordFormHeading.attributes().iscomplete).toBe('true')
							expect(wrapper.find(selectors.appPasswordInput).attributes().value).toBe(appPassword)
							expect(wrapper.find(selectors.appPasswordResetButton).exists()).toBe(false)
							expect(wrapper.find(selectors.appPasswordSubmitButton).text()).toBe('Done, complete setup')

							expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
							expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
							toMatchSerializedSnapshot(wrapper.html())
						})
						it('should set app password form to view mode on "Done" action', async () => {
							const appPassword = '12345678'
							saveAdminConfig.mockImplementation(() => Promise.resolve({
								data: {
									oPUserAppPassword: appPassword,
								},
							}))
							expect(wrapper.vm.appPassword).toBe(null)
							await wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(false)
							expect(wrapper.find(selectors.appPasswordInput).attributes().value).toBe(appPassword)
							expect(wrapper.find(selectors.appPasswordResetButton).exists()).toBe(false)
							const appPasswordSubmitButton = wrapper.find(selectors.appPasswordSubmitButton)
							expect(appPasswordSubmitButton.text()).toBe('Done, complete setup')
							await appPasswordSubmitButton.vm.$emit('click')

							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
							expect(appPasswordSubmitButton.text()).toBe('Replace application password')
							toMatchSerializedSnapshot(wrapper.html())
						})

						describe('disable project folder immediately after complete setup', () => {
							it('should show the correct label and form', async () => {
								const appPassword = '12345678'
								saveAdminConfig.mockImplementation(() => Promise.resolve({
									data: {
										oPUserAppPassword: appPassword,
									},
								}))
								const props = structuredClone(defaultProps)
								props.formState.projectFolder.complete = true
								const wrapper = getWrapper({ props })

								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								wrapper.find(selectors.appPasswordSubmitButton).vm.$emit('click')
								await flushPromises()
								// edit mode
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// disable project folder
								wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

								// enable project folder
								wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
								expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

								// disable project folder
								wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

								// save
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(saveAdminConfig).toHaveBeenCalledWith({
									setup_project_folder: false,
									setup_app_password: false,
								})
								expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
								expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
								expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Inactive')
								expect(wrapper.find(selectors.projectFolderFormHeading).attributes().issetupcompletewithoutprojectfolders).toBe('true')
							})
							it('enable-disable-enable project folder flow should work', async () => {
								const appPassword = '12345678'
								saveAdminConfig.mockImplementation(() => Promise.resolve({
									data: {
										oPUserAppPassword: appPassword,
									},
								}))
								const props = structuredClone(defaultProps)
								props.formState.projectFolder.complete = true
								const wrapper = getWrapper({ props })

								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								wrapper.find(selectors.appPasswordSubmitButton).vm.$emit('click')
								await flushPromises()

								// edit
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// disable project folder
								wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								// save
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(saveAdminConfig).toHaveBeenCalledWith({
									setup_project_folder: false,
									setup_app_password: false,
								})
								saveAdminConfig.mockReset()
								// mock again after reset
								saveAdminConfig.mockImplementation(() => Promise.resolve({
									data: {
										oPUserAppPassword: appPassword,
									},
								}))
								getProjectFolderStatus.mockImplementation(() => Promise.resolve({
									data: {
										result: true,
									},
								}))

								// edit
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// re-enable project folder
								wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
								// save
								wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(saveAdminConfig).toHaveBeenCalledWith({
									setup_project_folder: false,
									setup_app_password: true,
								})

								// save app password
								const appPasswordSubmitButton = wrapper.find(selectors.appPasswordSubmitButton)
								expect(appPasswordSubmitButton.text()).toBe('Done, complete setup')
								appPasswordSubmitButton.vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
							})
						})
					})

					describe('upon failure', () => {
						it.each([
							[
								'should set the user already exists error message and error details when user already exists',
								{
									error: 'The user "OpenProject" already exists',
									errorDescription: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],
							[
								'should set the team folder name already exists error message and error details when team folder already exists',
								{
									error: 'The team folder name "OpenProject" already exists',
									errorDescription: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],
							[
								'should set the group already exists error message and error details when group already exists',
								{
									error: 'The group "OpenProject" already exists',
									errorDescription: 'Setting up the OpenProject user, group and team folder was not possible. Please check this {htmlLink} on how to resolve this situation.',
								},
							],
						])('%s', async (name, expected) => {
							const props = structuredClone(defaultProps)
							const wrapper = getWrapper({ props })

							const errResponse = new Error('Request failed')
							errResponse.response = {}
							errResponse.response.data = {}
							errResponse.response.data.error = expected.error
							saveAdminConfig.mockImplementation(() => Promise.reject(errResponse))

							const spySetAppPasswordFormToEditMode = jest.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
							const spySetProjectFolderFormToViewMode = jest.spyOn(wrapper.vm, 'setProjectFolderFormToViewMode')
							expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
							wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(saveAdminConfig).toHaveBeenCalledTimes(1)
							expect(saveAdminConfig).toHaveBeenCalledWith({
								setup_app_password: true,
								setup_project_folder: true,
							})

							expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
							expect(spySetAppPasswordFormToEditMode).toHaveBeenCalledTimes(0)
							expect(spySetProjectFolderFormToViewMode).toHaveBeenCalledTimes(0)
							expect(wrapper.emitted().formcomplete).toBe(undefined)
							expect(showSuccess).toHaveBeenCalledTimes(0)

							expect(wrapper.vm.projectFolderSetupError).toBe(expected.error)
							expect(showError).toHaveBeenCalledTimes(1)
							expect(showError).toHaveBeenCalledWith(`Failed to save OpenProject admin options: ${expected.error}`)

							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().haserror).toBe('true')
							expect(wrapper.find(selectors.noteCardTitle).text()).toBe(expected.error)
							expect(wrapper.find(selectors.noteCardDescription).text()).toBe(expected.errorDescription)

							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe(undefined)
							expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe('true')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
							toMatchSerializedSnapshot(wrapper.html())
						})
					})
				})

				describe('teamfolders app error', function() {
					let wrapper
					beforeEach(async () => {
						const props = structuredClone(defaultProps)
						props.projectFolderInfo = {
							...defaultProps.projectFolderInfo,
							app: {
								enabled: false,
							},
						}
						wrapper = getWrapper({ props })
					})

					it('should show error message if project folder is enabled', async () => {
						expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
						expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)

						const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
						expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe(undefined)
						expect(projectFolderFormHeading.attributes().iscomplete).toBe(undefined)
						expect(projectFolderFormHeading.attributes().isdisabled).toBe(undefined)
						expect(projectFolderFormHeading.attributes().haserror).toBe('true')

						expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
						expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

						expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
						const errorNote = wrapper.find(selectors.errorNote)
						expect(errorNote.exists()).toBe(true)
						expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
						expect(errorNote.attributes().errorlink).toBe(appLinks.groupfolders.installLink)
						expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
						expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe('true')

						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should not show error message if project folder is disabled', async () => {
						wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
						await flushPromises()

						expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
						expect(wrapper.find(selectors.projectFolderDisabledDescription).exists()).toBe(true)
						expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe(undefined)

						expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
						expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
						expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

						toMatchSerializedSnapshot(wrapper.html())
					})

				})
			})

			describe('disable project folder after complete setup', function() {
				let wrapper
				beforeEach(async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo.freshSetup = false
					props.projectFolderInfo.hasAppPassword = true
					props.projectFolderInfo.folderStatus.status = true
					wrapper = getWrapper({ props })
				})

				it('should not call server on keep current', async () => {
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormHeading).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe('true')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
					expect(wrapper.find(selectors.appPasswordFormHeading).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe(undefined)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe('true')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
					expect(wrapper.find(selectors.appPasswordFormHeading).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
					expect(saveAdminConfig).toHaveBeenCalledTimes(0)
					expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
				})
				it('should hide app password form', async () => {
					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe(undefined)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
				it('should set project folder status to "Inactive" on save', async () => {
					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
					await flushPromises()

					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledTimes(1)
					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: false,
						setup_project_folder: false,
					})
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.emitted().formcomplete.length).toBe(2)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
					expect(showSuccess).toHaveBeenCalledTimes(1)

					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Inactive')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
			})

			describe('enable project folder after complete setup', function() {
				let wrapper
				const appPassword = '12345678'
				saveAdminConfig.mockImplementation(() => Promise.resolve({
					data: {
						oPUserAppPassword: appPassword,
					},
				}))

				beforeEach(async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo.freshSetup = false
					props.projectFolderInfo.hasAppPassword = false
					props.projectFolderInfo.folderStatus.status = false
					wrapper = getWrapper({ props })
				})

				it('should not call server on keep current', async () => {
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)

					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe(undefined)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe('true')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().checked).toBe(undefined)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledTimes(0)
					expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Inactive')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
				it('should show project folder status as "Inactive"', async () => {
					const appPassword = '12345678'
					saveAdminConfig.mockImplementation(() => Promise.resolve({
						data: {
							oPUserAppPassword: appPassword,
						},
					}))

					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo.freshSetup = false
					props.projectFolderInfo.hasAppPassword = false
					props.projectFolderInfo.folderStatus.status = false
					const wrapper = getWrapper({ props })

					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					wrapper.find(selectors.projectFolderSetupSwitch).vm.$emit('update:checked', true)
					await flushPromises()

					wrapper.find(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledTimes(1)
					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: true,
						setup_project_folder: true,
					})
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
					expect(wrapper.emitted().formcomplete.length).toBe(2)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
					expect(wrapper.vm.appPassword).toBe(appPassword)
					expect(showSuccess).toHaveBeenCalledTimes(1)

					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.appPasswordInput).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordSubmitButton).exists()).toBe(true)
				})
			})

			describe('reset app password', () => {
				let wrapper
				beforeEach(async () => {
					const props = structuredClone(defaultProps)
					props.formState.projectFolder.complete = true
					props.projectFolderInfo = {
						...defaultProps.projectFolderInfo,
						freshSetup: false,
						hasAppPassword: true,
						folderStatus: {
							status: true,
						},
					}
					wrapper = getWrapper({ props })
				})

				it('should trigger a confirm dialog', async () => {
					const spyConfirmDialog = jest.spyOn(global.OC.dialogs, 'confirmDestructive')

					const expectedConfirmText = 'If you proceed, your old application password for the OpenProject user will be deleted and you will receive a new OpenProject user password.'
					const expectedConfirmOpts = {
						cancel: 'Cancel',
						confirm: 'Yes, replace',
						confirmClasses: 'error',
						type: 70,
					}
					const expectedConfirmTitle = 'Replace user app password'

					wrapper.find(selectors.appPasswordResetButton).vm.$emit('click')
					await flushPromises()

					expect(spyConfirmDialog).toBeCalledTimes(1)
					expect(spyConfirmDialog).toBeCalledWith(
						expectedConfirmText,
						expectedConfirmTitle,
						expectedConfirmOpts,
						expect.any(Function),
						true,
					)
				})
				it('should replace old password with new one on confirm', async () => {
					const appPassword = '12345678'
					const spySetAppPasswordFormToEditMode = jest.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
					saveAdminConfig.mockImplementation(() => Promise.resolve({
						data: {
							oPUserAppPassword: appPassword,
						},
					}))
					getProjectFolderStatus.mockImplementation(() => Promise.resolve({
						data: {
							result: true,
						},
					}))
					await wrapper.vm.recreateAppPassword()
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: true,
					})
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
					expect(saveAdminConfig).toHaveBeenCalledTimes(1)
					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: true,
					})
					expect(spySetAppPasswordFormToEditMode).toHaveBeenCalledTimes(1)
					expect(wrapper.vm.appPassword).toBe('12345678')
				})
			})
		})
	})

	describe('Encryption warning after project folder setup', () => {
		it.each([
			[
				'should show warning when server side encryption is enabled but encryption for groupfolders is not enabled',
				{
					serverSideEnabled: true,
					teamFoldersEnabled: false,
				},
				true,
			],
			[
				'should not show warning when server side encryption and groupfolders encryption is enabled',
				{
					serverSideEnabled: true,
					teamFoldersEnabled: true,
				},
				false,
			],
			[
				'should not show warning when server side encryption not enabled but groupfolders encryption is enabled',
				{
					serverSideEnabled: false,
					teamFoldersEnabled: true,
				},
				false,
			],
		])('%s', (name, encryption, showWarning) => {
			const props = structuredClone(defaultProps)
			props.formState.projectFolder.complete = true
			props.projectFolderInfo = {
				...defaultProps.projectFolderInfo,
				freshSetup: false,
				hasAppPassword: true,
				folderStatus: {
					status: true,
				},
				encryption: {
					server_side_encryption_enabled: encryption.serverSideEnabled,
					encryption_enabled_for_groupfolders: encryption.teamFoldersEnabled,
				},
			}
			const wrapper = getWrapper({ props })

			expect(wrapper.find(selectors.noteCardWarning).exists()).toBe(showWarning)
			if (showWarning) {
				expect(wrapper.find(selectors.noteCard).attributes().type).toBe('warning')
				expect(wrapper.find(selectors.noteCardTitle).text()).toBe('Encryption for the Team Folders App is not enabled.')
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
