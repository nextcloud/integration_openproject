/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2026 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import flushPromises from 'flush-promises'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { ADMIN_SETTINGS_FORM, F_MODES, AUTH_METHOD } from '../../../../src/utils.js'
import { saveAdminConfig, createNextcloudOAuthClient } from '../../../../src/api/settings.js'
import FormOAuthSettings from '../../../../src/components/admin/FormOAuthSettings.vue'

// module mocks
vi.mock(import('@nextcloud/dialogs'), () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('../../../../src/api/settings.js'), () => ({
	saveAdminConfig: vi.fn(() => ''),
	createNextcloudOAuthClient: vi.fn(() => ''),
}))

const selectors = {
	opFormHeading: 'form-heading-stub[title="OpenProject OAuth settings"]',
	opFormContainer: '.oauth-settings--openproject',
	opClientIdLabel: 'field-value-stub[title="OpenProject OAuth client ID"]',
	opClientIdInput: 'text-input-stub[label="OpenProject OAuth client ID"]',
	opClientSecretLabel: 'field-value-stub[title="OpenProject OAuth client secret"]',
	opClientSecretInput: 'text-input-stub[label="OpenProject OAuth client secret"]',
	opSaveButton: 'nc-button-stub[data-test-id="submit-op-oauth-btn"]',
	opResetButton: 'nc-button-stub[data-test-id="reset-op-oauth-btn"]',
	ncFormHeading: 'form-heading-stub[title="Nextcloud OAuth client"]',
	ncFormContainer: '.oauth-settings--nextcloud',
	ncClientIdLabel: 'field-value-stub[title="Nextcloud OAuth client ID"]',
	ncClientIdInput: 'text-input-stub[label="Nextcloud OAuth client ID"]',
	ncClientSecretLabel: 'field-value-stub[title="Nextcloud OAuth client secret"]',
	ncClientSecretInput: 'text-input-stub[label="Nextcloud OAuth client secret"]',
	ncCreateButton: 'nc-button-stub[data-test-id="create-nc-oauth-btn"]',
	ncSaveButton: 'nc-button-stub[data-test-id="submit-nc-oauth-btn"]',
	ncResetButton: 'nc-button-stub[data-test-id="reset-nc-oauth-btn"]',
}

const formState = structuredClone(ADMIN_SETTINGS_FORM)
formState.serverHost.complete = true
formState.authenticationMethod.value = AUTH_METHOD.OAUTH2
formState.authenticationMethod.complete = true
const defaultProps = {
	formState,
	oauthSettings: {
		openproject_client_id: '',
		openproject_client_secret: '',
		nc_oauth_client: {
			nextcloud_client_id: '',
		},
	},
}

describe('Component: FormOAuthSettings', () => {
	afterEach(() => {
		vi.clearAllMocks()
		saveAdminConfig.mockReset()
		createNextcloudOAuthClient.mockReset()
	})

	describe('form states', () => {
		it('form state: incomplete authorization method', () => {
			const props = {
				formState: {
					...defaultProps.formState,
					authenticationMethod: {
						complete: false,
					},
				},
			}
			const wrapper = getWrapper({ props })

			const opFormHeading = wrapper.find(selectors.opFormHeading)
			const ncFormHeading = wrapper.find(selectors.ncFormHeading)
			const opFormContainer = wrapper.find(selectors.opFormContainer)
			const ncFormContainer = wrapper.find(selectors.ncFormContainer)
			expect(opFormHeading.exists()).toBe(true)
			expect(opFormHeading.attributes().isdisabled).toBe('true')
			expect(ncFormHeading.exists()).toBe(true)
			expect(ncFormHeading.attributes().isdisabled).toBe('true')
			expect(opFormContainer.exists()).toBe(false)
			expect(ncFormContainer.exists()).toBe(false)
			expect(wrapper.element).toMatchSnapshot()
		})
		it('form state: complete authorization method but incomplete oauth settings', () => {
			const wrapper = getWrapper()

			const opFormHeading = wrapper.find(selectors.opFormHeading)
			const ncFormHeading = wrapper.find(selectors.ncFormHeading)
			const opFormContainer = wrapper.find(selectors.opFormContainer)
			const ncFormContainer = wrapper.find(selectors.ncFormContainer)
			expect(opFormHeading.exists()).toBe(true)
			expect(opFormHeading.attributes().isdisabled).toBe('false')
			expect(ncFormHeading.exists()).toBe(true)
			expect(ncFormHeading.attributes().isdisabled).toBe('true')
			expect(opFormContainer.exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientIdInput).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientSecretLabel).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientSecretInput).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opSaveButton).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opSaveButton).attributes().disabled).toBe('true')
			expect(opFormContainer.find(selectors.opResetButton).exists()).toBe(false)
			expect(ncFormContainer.exists()).toBe(false)
			expect(wrapper.element).toMatchSnapshot()
		})
		it('form state: complete authorization method and complete OpenProject form but not Nextcloud', () => {
			const wrapper = getWrapper({
				props: {
					formState: {
						...defaultProps.formState,
						openprojectOauth: {
							complete: true,
						},
					},
					oauthSettings: {
						openproject_client_id: 'op-client',
						openproject_client_secret: 'op-client-secret',
						nc_oauth_client: {
							nextcloud_client_id: '',
						},
					},
				},
			})

			const opFormHeading = wrapper.find(selectors.opFormHeading)
			const ncFormHeading = wrapper.find(selectors.ncFormHeading)
			const opFormContainer = wrapper.find(selectors.opFormContainer)
			const ncFormContainer = wrapper.find(selectors.ncFormContainer)
			expect(opFormHeading.exists()).toBe(true)
			expect(opFormHeading.attributes().isdisabled).toBe('false')
			expect(ncFormHeading.exists()).toBe(true)
			expect(ncFormHeading.attributes().isdisabled).toBe('false')
			expect(opFormContainer.exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).attributes().value).toBe('op-client')
			expect(opFormContainer.find(selectors.opClientIdInput).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientSecretLabel).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientSecretLabel).attributes().value).toBe('op-client-secret')
			expect(opFormContainer.find(selectors.opClientSecretInput).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opSaveButton).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opResetButton).exists()).toBe(true)
			expect(ncFormContainer.exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientIdLabel).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncClientIdInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncClientSecretLabel).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncClientSecretInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncCreateButton).exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncSaveButton).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncResetButton).exists()).toBe(false)
			expect(wrapper.element).toMatchSnapshot()
		})
		it('form state: complete authorization method and complete Nextcloud form but not OpenProject', () => {
			const wrapper = getWrapper({
				props: {
					...defaultProps,
					oauthSettings: {
						openproject_client_id: '',
						openproject_client_secret: '',
						nc_oauth_client: {
							nextcloud_client_id: 'nc-client-id',
						},
					},
				},
			})

			const opFormHeading = wrapper.find(selectors.opFormHeading)
			const ncFormHeading = wrapper.find(selectors.ncFormHeading)
			const opFormContainer = wrapper.find(selectors.opFormContainer)
			const ncFormContainer = wrapper.find(selectors.ncFormContainer)
			expect(opFormHeading.exists()).toBe(true)
			expect(opFormHeading.attributes().isdisabled).toBe('false')
			expect(ncFormHeading.exists()).toBe(true)
			expect(ncFormHeading.attributes().isdisabled).toBe('false')
			expect(opFormContainer.exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientIdInput).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientSecretLabel).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientSecretInput).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opSaveButton).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opSaveButton).attributes().disabled).toBe('true')
			expect(opFormContainer.find(selectors.opResetButton).exists()).toBe(false)
			expect(ncFormContainer.exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientIdLabel).exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientIdLabel).attributes().value).toBe('nc-client-id')
			expect(ncFormContainer.find(selectors.ncClientIdInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncClientSecretLabel).exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientSecretLabel).attributes().value).toBe('***')
			expect(ncFormContainer.find(selectors.ncClientSecretInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncCreateButton).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncSaveButton).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncResetButton).exists()).toBe(true)
			expect(wrapper.element).toMatchSnapshot()
		})
		it('form state: complete OpenProject and Nextcloud oauth values', async () => {
			const wrapper = getWrapper({
				props: {
					...defaultProps,
					oauthSettings: {
						openproject_client_id: 'op-client',
						openproject_client_secret: 'op-client-secret',
						nc_oauth_client: {
							nextcloud_client_id: 'nc-client-id',
						},
					},
				},
			})

			const opFormHeading = wrapper.find(selectors.opFormHeading)
			const ncFormHeading = wrapper.find(selectors.ncFormHeading)
			const opFormContainer = wrapper.find(selectors.opFormContainer)
			const ncFormContainer = wrapper.find(selectors.ncFormContainer)
			expect(opFormHeading.exists()).toBe(true)
			expect(opFormHeading.attributes().isdisabled).toBe('false')
			expect(ncFormHeading.exists()).toBe(true)
			expect(ncFormHeading.attributes().isdisabled).toBe('false')
			expect(opFormContainer.exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientIdLabel).attributes().value).toBe('op-client')
			expect(opFormContainer.find(selectors.opClientIdInput).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opClientSecretLabel).exists()).toBe(true)
			expect(opFormContainer.find(selectors.opClientSecretLabel).attributes().value).toBe('op-client-secret')
			expect(opFormContainer.find(selectors.opClientSecretInput).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opSaveButton).exists()).toBe(false)
			expect(opFormContainer.find(selectors.opResetButton).exists()).toBe(true)
			expect(ncFormContainer.exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientIdLabel).exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientIdLabel).attributes().value).toBe('nc-client-id')
			expect(ncFormContainer.find(selectors.ncClientIdInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncClientSecretLabel).exists()).toBe(true)
			expect(ncFormContainer.find(selectors.ncClientSecretLabel).attributes().value).toBe('***')
			expect(ncFormContainer.find(selectors.ncClientSecretInput).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncSaveButton).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncCreateButton).exists()).toBe(false)
			expect(ncFormContainer.find(selectors.ncResetButton).exists()).toBe(true)
			expect(wrapper.element).toMatchSnapshot()
		})
	})

	describe('OpenProject OAuth form', () => {
		describe('view mode: reset values', () => {
			let wrapper
			beforeEach(() => {
				wrapper = getWrapper({
					props: {
						...defaultProps,
						oauthSettings: {
							openproject_client_id: 'op-client',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: 'nc-client-id',
							},
						},
					},
				})
			})

			it('should mark form complete', async () => {
				expect(wrapper.emitted().formcomplete).toHaveLength(2)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
			})
			it('should trigger confirm dialog on click', async () => {
				const spyConfirmDialog = vi.spyOn(global.OC.dialogs, 'confirmDestructive')
				const resetButton = wrapper.findComponent(selectors.opResetButton)
				await resetButton.vm.$emit('click')
				await flushPromises()

				expect(spyConfirmDialog).toHaveBeenCalledTimes(1)
			})
			it('should clear OpenProject OAuth values on confirm', async () => {
				await wrapper.vm.confirmResetOpenProjectClient()
				await flushPromises()

				expect(saveAdminConfig).toHaveBeenCalledTimes(1)
				expect(saveAdminConfig).toHaveBeenCalledWith({
					openproject_client_id: '',
					openproject_client_secret: '',
				})
				expect(wrapper.vm.currentOpenProjectForm.clientId).toBe('')
				expect(wrapper.vm.currentOpenProjectForm.clientSecret).toBe('')
				expect(wrapper.vm.openprojectFormMode).toBe(F_MODES.EDIT)
				const opFormContainer = wrapper.find(selectors.opFormContainer)
				expect(opFormContainer.exists()).toBe(true)
				expect(opFormContainer.find(selectors.opClientIdLabel).exists()).toBe(false)
				expect(opFormContainer.find(selectors.opClientIdInput).exists()).toBe(true)
				expect(opFormContainer.find(selectors.opClientSecretLabel).exists()).toBe(false)
				expect(opFormContainer.find(selectors.opClientSecretInput).exists()).toBe(true)
				expect(opFormContainer.find(selectors.opSaveButton).exists()).toBe(true)
				expect(opFormContainer.find(selectors.opSaveButton).attributes().disabled).toBe('true')
				expect(opFormContainer.find(selectors.opResetButton).exists()).toBe(false)
			})
		})
		describe('edit mode', () => {
			let wrapper
			beforeEach(() => {
				wrapper = getWrapper()
			})

			it('should not enable save button if the form is incomplete', async () => {
				const saveButton = wrapper.find(selectors.opSaveButton)
				expect(saveButton.attributes().disabled).toBe('true')

				await wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', 'op-client-id')

				expect(saveButton.attributes().disabled).toBe('true')
			})
			it('should enable save button if the form is complete', async () => {
				const saveButton = wrapper.find(selectors.opSaveButton)
				expect(saveButton.attributes().disabled).toBe('true')

				await wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', 'op-client-id')
				await wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', 'op-client-secret')

				expect(saveButton.attributes().disabled).toBe('false')
			})

			describe('save action', () => {
				const opClientId = 'op-client-id'
				const opClientSecret = 'op-client-secret'

				describe('when the save is successful', () => {
					it('should show success and set form to view mode', async () => {
						createNextcloudOAuthClient.mockImplementation(() => Promise.resolve({
							data: {
								nextcloud_client_id: 'nc-client-id',
								nextcloud_client_secret: 'nc-client-secret',
							},
						}))
						const wrapper = getWrapper()
						const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient')
						const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')
						wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', opClientId)
						wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', opClientSecret)
						wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)
						expect(saveAdminConfig).toHaveBeenCalledWith({
							openproject_client_id: opClientId,
							openproject_client_secret: opClientSecret,
						})

						expect(wrapper.vm.savedOpenProjectForm.clientId).toBe(opClientId)
						expect(wrapper.vm.savedOpenProjectForm.clientSecret).toBe(opClientSecret)
						expect(wrapper.vm.openprojectFormMode).toBe(F_MODES.VIEW)
						expect(wrapper.find(selectors.opClientIdLabel).exists()).toBe(true)
						expect(wrapper.find(selectors.opClientIdInput).exists()).toBe(false)
						expect(wrapper.find(selectors.opClientSecretLabel).exists()).toBe(true)
						expect(wrapper.find(selectors.opClientSecretInput).exists()).toBe(false)
						expect(wrapper.find(selectors.opResetButton).exists()).toBe(true)

						expect(wrapper.emitted().formcomplete).toHaveLength(1)
						expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

						expect(spyCreateNextcloudClient).toHaveBeenCalledTimes(1)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)

						expect(wrapper.vm.nextcloudFormMode).toBe(F_MODES.EDIT)
						expect(wrapper.find(selectors.ncClientIdInput).exists()).toBe(true)
						expect(wrapper.findComponent(selectors.ncClientIdInput).attributes().modelvalue).toBe('nc-client-id')
						expect(wrapper.find(selectors.ncClientIdLabel).exists()).toBe(false)
						expect(wrapper.find(selectors.ncClientSecretInput).exists()).toBe(true)
						expect(wrapper.findComponent(selectors.ncClientSecretInput).attributes().modelvalue).toBe('nc-client-secret')
						expect(wrapper.find(selectors.ncClientSecretLabel).exists()).toBe(false)
						expect(wrapper.find(selectors.ncSaveButton).exists()).toBe(true)
						expect(wrapper.find(selectors.ncCreateButton).exists()).toBe(false)
						expect(wrapper.find(selectors.ncResetButton).exists()).toBe(false)
					})

					it('should not create Nextcloud OAuth client if already present', async () => {
						const wrapper = getWrapper({
							props: {
								...defaultProps,
								oauthSettings: {
									openproject_client_id: '',
									openproject_client_secret: '',
									nc_oauth_client: {
										nextcloud_client_id: 'nc-client-id',
									},
								},
							},
						})
						const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient')
						const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')
						wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', opClientId)
						wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', opClientSecret)
						wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)
						expect(spyCreateNextcloudClient).not.toHaveBeenCalled()
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)
					})
				})

				describe('when the save fails', () => {
					it('should show error and keep the form in edit mode', async () => {
						saveAdminConfig.mockImplementation(() => Promise.reject(new Error('Failure')))
						const wrapper = getWrapper()
						const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient').mockImplementation(() => vi.fn())
						const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')

						wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', opClientId)
						wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', opClientSecret)
						wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)

						expect(wrapper.vm.savedOpenProjectForm.clientId).toBe('')
						expect(wrapper.vm.savedOpenProjectForm.clientSecret).toBe('')
						expect(wrapper.vm.openprojectFormMode).toBe(F_MODES.EDIT)
						expect(spyCreateNextcloudClient).toHaveBeenCalledTimes(0)

						expect(wrapper.vm.openprojectTokenRevokeStatus).toBeNull()
						expect(showSuccess).toHaveBeenCalledTimes(0)
						expect(showError).toHaveBeenCalledTimes(1)
						expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)
					})
					it('should show error if Nextcloud OAuth client creation fails', async () => {
						createNextcloudOAuthClient.mockImplementation(() => Promise.reject(new Error('Failure')))
						const wrapper = getWrapper()
						const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient')
						const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')

						wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', opClientId)
						wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', opClientSecret)
						wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
						await flushPromises()

						expect(saveAdminConfig).toHaveBeenCalledTimes(1)
						expect(wrapper.vm.savedOpenProjectForm.clientId).toBe(opClientId)
						expect(wrapper.vm.savedOpenProjectForm.clientSecret).toBe(opClientSecret)
						expect(wrapper.vm.openprojectFormMode).toBe(F_MODES.VIEW)

						expect(wrapper.emitted().formcomplete).toHaveLength(1)
						expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

						expect(spyCreateNextcloudClient).toHaveBeenCalledTimes(1)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(1)
						expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)
					})
				})
			})
		})
	})

	describe('Nextcloud OAuth form', () => {
		describe('view mode: reset button', () => {
			let wrapper
			beforeEach(() => {
				wrapper = getWrapper({
					props: {
						...defaultProps,
						oauthSettings: {
							openproject_client_id: 'op-client',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: 'nc-client-id',
							},
						},
					},
				})
			})

			it('should mark form complete', async () => {
				expect(wrapper.emitted().formcomplete).toHaveLength(2)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
			})
			it('should trigger confirm dialog on click', async () => {
				expect(wrapper.emitted().formcomplete).toHaveLength(2)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
				const spyConfirmDialog = vi.spyOn(global.OC.dialogs, 'confirmDestructive')
				const resetButton = wrapper.findComponent(selectors.ncResetButton)
				resetButton.vm.$emit('click')
				await flushPromises()

				expect(spyConfirmDialog).toHaveBeenCalledTimes(1)
			})
			it('should create new client on confirm', async () => {
				createNextcloudOAuthClient.mockImplementationOnce(() => Promise.resolve({
					data: {
						nextcloud_client_id: 'new-client-id',
						nextcloud_client_secret: 'new-client-secret',
					},
				}))
				await wrapper.vm.createNextcloudClient()
				await flushPromises()

				expect(createNextcloudOAuthClient).toHaveBeenCalledTimes(1)
				expect(wrapper.vm.savedNextcloudForm.clientId).toBe('new-client-id')
				expect(wrapper.vm.savedNextcloudForm.clientSecret).toBe('new-client-secret')
				expect(wrapper.vm.nextcloudFormMode).toBe(F_MODES.EDIT)

				const ncFormContainer = wrapper.find(selectors.ncFormContainer)
				expect(ncFormContainer.exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientIdLabel).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncClientIdInput).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientIdInput).attributes().modelvalue).toBe('new-client-id')
				expect(ncFormContainer.find(selectors.ncClientSecretLabel).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncClientSecretInput).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientSecretInput).attributes().modelvalue).toBe('new-client-secret')

				expect(ncFormContainer.find(selectors.ncSaveButton).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncSaveButton).attributes().disabled).toBe('false')
				expect(ncFormContainer.find(selectors.ncSaveButton).text()).toBe('Yes, I have copied these values')
				expect(ncFormContainer.find(selectors.ncResetButton).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncCreateButton).exists()).toBe(false)
			})
		})

		describe('create button', () => {
			let wrapper
			beforeEach(() => {
				createNextcloudOAuthClient.mockImplementationOnce(() => Promise.resolve({
					data: {
						nextcloud_client_id: 'nc-client-id',
						nextcloud_client_secret: 'nc-client-secret',
					},
				}))
				wrapper = getWrapper({
					props: {
						...defaultProps,
						oauthSettings: {
							openproject_client_id: 'op-client',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: '',
							},
						},
						formState: {
							...defaultProps.formState,
							openprojectOauth: {
								complete: true,
							},
						},
					},
				})
			})
			it('should show create button if other settings are set but not Nextcloud OAuth client', async () => {
				const createButton = wrapper.find(selectors.ncCreateButton)
				expect(createButton.exists()).toBe(true)
				expect(createButton.text()).toBe('Create Nextcloud OAuth values')
				expect(wrapper.find(selectors.ncResetButton).exists()).toBe(false)
				expect(wrapper.find(selectors.ncSaveButton).exists()).toBe(false)
			})

			it('should create Nextcloud OAuth client and set the form mode to edit', async () => {
				const createButton = wrapper.findComponent(selectors.ncCreateButton)
				createButton.vm.$emit('click')
				await flushPromises()

				expect(createNextcloudOAuthClient).toHaveBeenCalledTimes(1)
				expect(wrapper.vm.savedNextcloudForm.clientId).toBe('nc-client-id')
				expect(wrapper.vm.savedNextcloudForm.clientSecret).toBe('nc-client-secret')
				expect(wrapper.vm.nextcloudFormMode).toBe(F_MODES.EDIT)

				expect(wrapper.emitted().formcomplete).toHaveLength(1)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

				const ncFormContainer = wrapper.find(selectors.ncFormContainer)
				expect(ncFormContainer.exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientIdLabel).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncClientIdInput).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientIdInput).attributes().modelvalue).toBe('nc-client-id')
				expect(ncFormContainer.find(selectors.ncClientSecretLabel).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncClientSecretInput).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncClientSecretInput).attributes().modelvalue).toBe('nc-client-secret')

				expect(ncFormContainer.find(selectors.ncSaveButton).exists()).toBe(true)
				expect(ncFormContainer.find(selectors.ncSaveButton).attributes().disabled).toBe('false')
				expect(ncFormContainer.find(selectors.ncSaveButton).text()).toBe('Yes, I have copied these values')
				expect(ncFormContainer.find(selectors.ncResetButton).exists()).toBe(false)
				expect(ncFormContainer.find(selectors.ncCreateButton).exists()).toBe(false)
			})
		})

		describe('edit mode', () => {
			it('should set the form to view mode on save', async () => {
				createNextcloudOAuthClient.mockImplementationOnce(() => Promise.resolve({
					data: {
						nextcloud_client_id: 'new-client-id',
						nextcloud_client_secret: 'new-client-secret',
					},
				}))
				const wrapper = getWrapper({
					props: {
						...defaultProps,
						oauthSettings: {
							openproject_client_id: 'op-client',
							openproject_client_secret: 'op-client-secret',
							nc_oauth_client: {
								nextcloud_client_id: 'nc-client-id',
							},
						},
						formState: {
							...defaultProps.formState,
							openprojectOauth: {
								complete: true,
							},
						},
					},
				})
				const resetButton = wrapper.findComponent(selectors.ncResetButton)
				resetButton.vm.$emit('click')
				await wrapper.vm.createNextcloudClient()
				await flushPromises()

				expect(wrapper.vm.nextcloudFormMode).toBe(F_MODES.EDIT)
				expect(wrapper.find(selectors.ncClientIdInput).exists()).toBe(true)
				expect(wrapper.find(selectors.ncClientSecretInput).exists()).toBe(true)
				expect(wrapper.find(selectors.ncClientSecretInput).exists()).toBe(true)
				expect(wrapper.find(selectors.ncResetButton).exists()).toBe(false)
				expect(wrapper.find(selectors.ncCreateButton).exists()).toBe(false)

				const saveButton = wrapper.findComponent(selectors.ncSaveButton)
				expect(saveButton.exists()).toBe(true)
				saveButton.vm.$emit('click')
				await flushPromises()

				expect(wrapper.vm.nextcloudFormMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.ncClientIdInput).exists()).toBe(false)
				expect(wrapper.find(selectors.ncClientIdLabel).exists()).toBe(true)
				expect(wrapper.find(selectors.ncClientSecretInput).exists()).toBe(false)
				expect(wrapper.find(selectors.ncClientSecretLabel).exists()).toBe(true)
				expect(wrapper.find(selectors.ncResetButton).exists()).toBe(true)
				expect(wrapper.find(selectors.ncCreateButton).exists()).toBe(false)
				expect(wrapper.find(selectors.ncSaveButton).exists()).toBe(false)
			})
		})
	})

	describe('revoke OpenProject OAuth token', () => {
		it('should show success when revoke status is success', async () => {
			saveAdminConfig.mockImplementationOnce(() => Promise.resolve({
				data: {
					oPOAuthTokenRevokeStatus: 'success',
				},
			}))
			const wrapper = getWrapper()
			const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient').mockImplementation(() => vi.fn())
			const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')
			wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', 'id')
			wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', 'secret')
			wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
			await flushPromises()

			expect(spyCreateNextcloudClient).toHaveBeenCalledTimes(1)
			expect(wrapper.vm.openprojectTokenRevokeStatus).toBe('success')
			expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)
			expect(showSuccess).toHaveBeenCalledTimes(2)
			expect(showError).toHaveBeenCalledTimes(0)
			expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')
			expect(showSuccess).toHaveBeenCalledWith('Successfully revoked users\' OpenProject OAuth access tokens')

		})
		it.each([
			['connection_error', 'Failed to perform revoke request due to connection error with the OpenProject server'],
			['other_error', 'Failed to revoke some users\' OpenProject OAuth access tokens'],
		])('should show error message on various failure', async (errorCode, errorMessage) => {
			saveAdminConfig.mockImplementationOnce(() => Promise.resolve({
				data: {
					oPOAuthTokenRevokeStatus: errorCode,
				},
			}))
			const wrapper = getWrapper()
			const spyCreateNextcloudClient = vi.spyOn(wrapper.vm, 'createNextcloudClient').mockImplementation(() => vi.fn())
			const spyNotifyOpenProjectTokenRevoke = vi.spyOn(wrapper.vm, 'notifyOpenProjectTokenRevoke')
			wrapper.findComponent(selectors.opClientIdInput).vm.$emit('update:modelValue', 'id')
			wrapper.findComponent(selectors.opClientSecretInput).vm.$emit('update:modelValue', 'secret')
			wrapper.findComponent(selectors.opSaveButton).vm.$emit('click')
			await flushPromises()

			expect(spyCreateNextcloudClient).toHaveBeenCalledTimes(1)
			expect(wrapper.vm.openprojectTokenRevokeStatus).toBe(errorCode)
			expect(spyNotifyOpenProjectTokenRevoke).toHaveBeenCalledTimes(1)

			expect(showSuccess).toHaveBeenCalledTimes(1)
			expect(showError).toHaveBeenCalledTimes(1)
			expect(showSuccess).toHaveBeenCalledWith('OpenProject admin options saved')
			expect(showError).toHaveBeenCalledWith(errorMessage)
		})
	})
})

function getWrapper({ data = {}, props = {} } = {}) {
	return shallowMount(FormOAuthSettings, {
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
