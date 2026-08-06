/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2025 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showSuccess, showError } from '@nextcloud/dialogs'
import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import flushPromises from 'flush-promises'

import { toMatchSerializedSnapshot } from '../../utils.js'
import FormProjectFolder from '../../../../src/components/admin/FormProjectFolder.vue'
import { F_MODES, AUTH_METHOD, ADMIN_SETTINGS_FORM } from '../../../../src/utils.js'
import { appLinks } from '../../../../src/constants/links.js'
import { messagesFmt, messages } from '../../../../src/constants/messages.js'
import { saveAdminConfig, getProjectFolderStatus } from '../../../../src/api/settings.js'

vi.mock(import('@nextcloud/dialogs'), () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('../../../../src/api/settings.js'), () => ({
	saveAdminConfig: vi.fn(() => ''),
	getProjectFolderStatus: vi.fn(),
}))

const writeText = vi.fn()
Object.assign(global.navigator, {
	clipboard: {
		writeText,
	},
})

const selectors = {
	errorNote: 'error-note-stub',
	noteCard: 'nc-note-card-stub',
	noteCardWarning: 'nc-note-card-stub .note-card--warning-description',
	noteCardTitle: 'nc-note-card-stub .note-card--title',
	noteCardDescription: 'nc-note-card-stub .note-card--error-description',
	projectFolderFormHeading: '.project-folder-setup form-heading-stub',
	projectFolderFormContainer: '.project-folder-form-container',
	projectFolderFormStatus: '.project-folder-status',
	projectFolderFormStatusLabel: '.project-folder-status-value',
	projectFolderForm: '.project-folder-form',
	projectFolderSetupSwitch: '.project-folder-form nc-checkbox-radio-switch-stub',
	projectFolderDescription: '.project-folder-description',
	projectFolderDisabledDescription: '.complete-without-groupfolders',
	projectFolderActionButton: '.project-folder-form-container nc-button-stub',
	appPasswordFormContainer: '.app-password-form-container',
	appPasswordFormHeading: '.app-password-form-container form-heading-stub',
	appPasswordFormLabel: '.app-password-form-container field-value-stub',
	appPasswordInput: '.app-password-form-container text-input-stub',
	appPasswordSubmitButton: 'nc-button-stub[data-test-id="submit-op-system-password-form-btn"]',
	appPasswordResetButton: 'nc-button-stub[data-test-id="reset-user-app-password"]',
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
				expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
				expect(projectFolderFormHeading.attributes().haserror).toBe('false')
				expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('false')
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
					expect(wrapper.emitted().formcomplete).toHaveLength(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
					expect(projectFolderFormHeading.attributes().haserror).toBe('false')
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('false')
					expect(projectFolderFormHeading.attributes().index).toBe(defaultProps.formOrder.toString())

					expect(wrapper.find(selectors.projectFolderFormContainer).exists()).toBe(true)
					const projectFolderFormStatus = wrapper.find(selectors.projectFolderFormStatus)
					expect(projectFolderFormStatus.exists()).toBe(true)
					expect(projectFolderFormStatus.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')
					expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)

					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)
					const appPasswordFormHeading = wrapper.find(selectors.appPasswordFormHeading)
					expect(appPasswordFormHeading.attributes().isdisabled).toBe('false')
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
					expect(wrapper.emitted().formcomplete).toHaveLength(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
					expect(projectFolderFormHeading.attributes().haserror).toBe('false')

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
					expect(wrapper.emitted().formcomplete).toHaveLength(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
					expect(projectFolderFormHeading.attributes().haserror).toBe('false')

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
					expect(wrapper.emitted().formcomplete).toHaveLength(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
					expect(projectFolderFormHeading.attributes().haserror).toBe('false')

					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe('false')

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
					expect(wrapper.emitted().formcomplete).toHaveLength(1)
					expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('false')
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('true')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
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
					expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe('false')

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
					expect(wrapper.emitted().formcomplete).toBeUndefined()
					expect(wrapper.find(selectors.noteCard).exists()).toBe(false)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
					expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('false')
					expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
					expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
					expect(projectFolderFormHeading.attributes().haserror).toBe('false')

					const projectFolderForm = wrapper.find(selectors.projectFolderForm)
					expect(projectFolderForm.exists()).toBe(true)
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('true')
					expect(wrapper.findAll(selectors.projectFolderDescription)).toHaveLength(2)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
					const projectFolderActionButton = projectFolderForm.find(selectors.projectFolderActionButton)
					expect(projectFolderActionButton.text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
					expect(wrapper.find(selectors.projectFolderDisabledDescription).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
				it('should show disabled form fields if project folder is disabled', async () => {
					const projectFolderSetupSwitch = wrapper.findComponent(selectors.projectFolderSetupSwitch)
					projectFolderSetupSwitch.vm.$emit('update:modelValue', false)
					await flushPromises()

					expect(projectFolderSetupSwitch.attributes().modelvalue).toBe('false')
					const projectFolderActionButton = wrapper.find(selectors.projectFolderActionButton)
					expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(projectFolderActionButton.text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})

				describe('on save: disabled project folder', () => {
					it('should set status "Inactive"', async () => {
						const spySetAppPasswordFormToEditMode = vi.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
						const projectFolderSetupSwitch = wrapper.findComponent(selectors.projectFolderSetupSwitch)
						projectFolderSetupSwitch.vm.$emit('update:modelValue', false)
						await wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)
						expect(saveAdminConfig).toHaveBeenCalledWith({
							setup_app_password: false,
							setup_project_folder: false,
						})
						expect(spySetAppPasswordFormToEditMode).not.toHaveBeenCalled()
						expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
						expect(wrapper.vm.loading).toBe(false)
						expect(wrapper.emitted().formcomplete).toHaveLength(1)
						expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')

						expect(wrapper.vm.appPassword).toBeNull()
						expect(wrapper.vm.appPasswordCreated).toBe(false)
						expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
						expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
						const projectFolderFormHeading = wrapper.find(selectors.projectFolderFormHeading)
						expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
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

							wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
							wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()
							// edit mode
							wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// enable project folder
							wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// disable project folder
							wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

							// enable project folder
							wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
							await flushPromises()
							expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

							// save
							wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(saveAdminConfig).toHaveBeenCalledWith({
								setup_project_folder: true,
								setup_app_password: true,
							})
							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
							expect(wrapper.find(selectors.projectFolderForm).exists()).toBe(false)
							expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().issetupcompletewithoutprojectfolders).toBe('false')
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
							// save app password
							const appPasswordSubmitButton = wrapper.findComponent(selectors.appPasswordSubmitButton)
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
							const spySetAppPasswordFormToEditMode = vi.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
							expect(wrapper.vm.appPassword).toBeNull()
							expect(wrapper.vm.appPasswordCreated).toBe(false)
							await wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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
							expect(wrapper.vm.appPasswordCreated).toBe(true)
							expect(wrapper.emitted().formcomplete).toHaveLength(1)
							expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
							expect(showSuccess).toHaveBeenCalledTimes(1)
							expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')

							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(true)
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().issetupcompletewithoutprojectfolders).toBe('false')
							expect(wrapper.find(selectors.projectFolderFormStatusLabel).text()).toContain(': Active')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe('Edit project folders')

							expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(true)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(false)
							const appPasswordFormHeading = wrapper.find(selectors.appPasswordFormHeading)
							expect(appPasswordFormHeading.attributes().isdisabled).toBe('false')
							expect(appPasswordFormHeading.attributes().iscomplete).toBe('true')
							expect(wrapper.find(selectors.appPasswordInput).attributes().modelvalue).toBe(appPassword)
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
							expect(wrapper.vm.appPassword).toBeNull()
							await wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
							await flushPromises()

							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(false)
							expect(wrapper.find(selectors.appPasswordInput).attributes().modelvalue).toBe(appPassword)
							expect(wrapper.find(selectors.appPasswordResetButton).exists()).toBe(false)
							const appPasswordSubmitButton = wrapper.findComponent(selectors.appPasswordSubmitButton)
							expect(appPasswordSubmitButton.text()).toBe('Done, complete setup')
							await appPasswordSubmitButton.vm.$emit('click')

							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)
							expect(appPasswordSubmitButton.exists()).toBe(false)
							expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
							expect(wrapper.findComponent(selectors.appPasswordResetButton).text()).toBe('Replace application password')
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

								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								wrapper.findComponent(selectors.appPasswordSubmitButton).vm.$emit('click')
								await flushPromises()
								// edit mode
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// disable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

								// enable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
								expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

								// disable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderDisabledDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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

								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								wrapper.findComponent(selectors.appPasswordSubmitButton).vm.$emit('click')
								await flushPromises()

								// edit
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// disable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// re-enable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(saveAdminConfig).toHaveBeenCalledWith({
									setup_project_folder: false,
									setup_app_password: true,
								})

								// save app password
								const appPasswordSubmitButton = wrapper.findComponent(selectors.appPasswordSubmitButton)
								expect(appPasswordSubmitButton.text()).toBe('Done, complete setup')
								appPasswordSubmitButton.vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)
							})
							it('complete-disable-enable project folder flow should work', async () => {
								const appPassword = '12345678'
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
								const props = structuredClone(defaultProps)
								props.formState.projectFolder.complete = true
								props.projectFolderInfo.freshSetup = false
								props.projectFolderInfo.hasAppPassword = true
								props.projectFolderInfo.folderStatus.status = true
								const wrapper = getWrapper({ props })

								// edit
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// disable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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

								// edit
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

								// re-enable project folder
								wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
								await flushPromises()
								expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
								// save
								wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
								await flushPromises()
								expect(saveAdminConfig).toHaveBeenCalledWith({
									setup_project_folder: false,
									setup_app_password: true,
								})

								// save app password
								const appPasswordSubmitButton = wrapper.findComponent(selectors.appPasswordSubmitButton)
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

							const spySetAppPasswordFormToEditMode = vi.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
							const spySetProjectFolderFormToViewMode = vi.spyOn(wrapper.vm, 'setProjectFolderFormToViewMode')
							expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
							expect(wrapper.vm.passwordFormMode).toBe(F_MODES.DISABLE)
							wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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
							expect(wrapper.emitted().formcomplete).toBeUndefined()
							expect(showSuccess).toHaveBeenCalledTimes(0)

							expect(wrapper.vm.projectFolderSetupError).toBe(expected.error)
							expect(showError).toHaveBeenCalledTimes(1)
							expect(showError).toHaveBeenCalledWith(`Failed to save OpenProject admin options: ${expected.error}`)

							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().haserror).toBe('true')
							expect(wrapper.find(selectors.noteCardTitle).text()).toBe(expected.error)
							expect(wrapper.find(selectors.noteCardDescription).text()).toBe(expected.errorDescription)

							expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
							expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('false')
							expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('true')
							expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.retrySetupWithProjectFolder)
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
						expect(projectFolderFormHeading.attributes().issetupcompletewithoutprojectfolders).toBe('false')
						expect(projectFolderFormHeading.attributes().iscomplete).toBe('false')
						expect(projectFolderFormHeading.attributes().isdisabled).toBe('false')
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
						wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
						await flushPromises()

						expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
						expect(wrapper.find(selectors.projectFolderDisabledDescription).exists()).toBe(true)
						expect(wrapper.find(selectors.projectFolderActionButton).attributes().disabled).toBe('false')

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
					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.VIEW)

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('true')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
					expect(wrapper.find(selectors.appPasswordFormHeading).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('false')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('true')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)
					expect(wrapper.find(selectors.appPasswordFormHeading).exists()).toBe(true)
					expect(wrapper.find(selectors.appPasswordFormLabel).exists()).toBe(true)

					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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
					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderFormHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('false')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithoutProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)
				})
				it('should set project folder status to "Inactive" on save', async () => {
					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
					await flushPromises()

					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledTimes(1)
					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: false,
						setup_project_folder: false,
					})
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.emitted().formcomplete).toHaveLength(2)
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
					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.EDIT)

					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('false')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('Let OpenProject create folders per project automatically')
					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('true')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.completeWithProjectFolderSetup)
					expect(wrapper.find(selectors.appPasswordFormContainer).exists()).toBe(false)

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', false)
					await flushPromises()

					expect(wrapper.find(selectors.projectFolderSetupSwitch).attributes().modelvalue).toBe('false')
					expect(wrapper.find(selectors.projectFolderFormStatus).exists()).toBe(false)
					expect(wrapper.find(selectors.projectFolderDescription).text()).toContain('We recommend using this functionality but it is not mandatory')
					expect(wrapper.find(selectors.projectFolderActionButton).text()).toBe(messages.projectFolderSetup.keepCurrentChange)

					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
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

					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					wrapper.findComponent(selectors.projectFolderSetupSwitch).vm.$emit('update:modelValue', true)
					await flushPromises()

					wrapper.findComponent(selectors.projectFolderActionButton).vm.$emit('click')
					await flushPromises()

					expect(saveAdminConfig).toHaveBeenCalledTimes(1)
					expect(saveAdminConfig).toHaveBeenCalledWith({
						setup_app_password: true,
						setup_project_folder: true,
					})
					expect(wrapper.vm.folderFormMode).toBe(F_MODES.VIEW)
					expect(wrapper.vm.passwordFormMode).toBe(F_MODES.EDIT)
					expect(wrapper.emitted().formcomplete).toHaveLength(2)
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
					const spyConfirmDialog = vi.spyOn(global.OC.dialogs, 'confirmDestructive')

					const expectedConfirmText = 'If you proceed, your old application password for the OpenProject user will be deleted and you will receive a new OpenProject user password.'
					const expectedConfirmOpts = {
						cancel: 'Cancel',
						confirm: 'Yes, replace',
						confirmClasses: 'error',
						type: 70,
					}
					const expectedConfirmTitle = 'Replace user app password'

					wrapper.findComponent(selectors.appPasswordResetButton).vm.$emit('click')
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
					const spySetAppPasswordFormToEditMode = vi.spyOn(wrapper.vm, 'setAppPasswordFormToEditMode')
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
		global: {
			mocks: {
				t: (app, msg) => msg,
			},
		},
		props: { ...defaultProps, ...props },
		data() {
			return data
		},
	})
}
