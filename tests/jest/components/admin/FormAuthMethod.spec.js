/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, vi } from 'vitest'
import flushPromises from 'flush-promises'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { ADMIN_SETTINGS_FORM, AUTH_METHOD, F_MODES } from '../../../../src/utils.js'
import { saveAdminConfig } from '../../../../src/api/settings.js'
import FormAuthMethod from '../../../../src/components/admin/FormAuthMethod.vue'
import { messagesFmt, messages } from '../../../../src/constants/messages.js'
import { appLinks } from '../../../../src/constants/links.js'

// module mocks
vi.mock(import('@nextcloud/dialogs'), () => ({
	getLanguage: vi.fn(() => ''),
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('../../../../src/api/settings.js'), () => ({
	saveAdminConfig: vi.fn(() => ''),
}))

const selectors = {
	formheading: 'form-heading-stub',
	formViewModeLabel: '.auth-method--label',
	oauthRadioBox: '#oauth-auth-method',
	ssoRadioBox: '#sso-auth-method',
	saveFormButton: '[data-test-id="save-auth-method"]',
	editFormButton: '[data-test-id="edit-auth-method"]',
	cancelFormButton: '[data-test-id="cancel-auth-method-edit"]',
	errorLabel: 'error-label-stub',
	errorNote: 'error-note-stub',
}

const defaultProps = {
	formState: JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM)),
	apps: {
		user_oidc: {
			enabled: true,
			supported: true,
			minimum_version: '1.0.0',
			name: 'OpenID Connect user backend',
		},
	},
}

const app = {
	userOidc: {
		name: 'OpenID Connect user backend',
		minimum_version: '1.0.0',
	},
}

describe('Component: FormAuthMethod', () => {
	const spyConfirmDialog = vi.spyOn(global.OC.dialogs, 'confirmDestructive')

	describe('initial incomplete form', () => {
		let wrapper
		beforeEach(() => {
			wrapper = getWrapper()
		})

		describe('server url not set', () => {
			it('should show form heading with disabled status', async () => {
				expect(wrapper.findComponent(selectors.formheading).attributes().isdisabled).toBe('true')
				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.html()).toMatchSnapshot()
			})
		})

		describe('current setting: authentication-method', () => {
			beforeEach(async () => {
				const formState = JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM))
				formState.serverHost.complete = true
				wrapper = getWrapper({ props: { formState } })
			})
			it('should show the required form fields', async () => {
				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.formheading).attributes().isdisabled).toBe('false')

				expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.html()).toMatchSnapshot()
			})
			it('should have enabled save button', async () => {
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				console.log(wrapper.findComponent(selectors.oauthRadioBox).attributes())
				expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OAUTH2)
				expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('false')
			})
			it('should save the setting on submit', async () => {
				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				expect(wrapper.vm.savedAuthMethod).toBeNull()

				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OIDC
				await flushPromises()
				expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OIDC)

				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(saveAdminConfig).toHaveBeenCalledWith({
					authorization_method: AUTH_METHOD.OIDC,
					openproject_client_id: null,
					openproject_client_secret: null,
					sso_provider_type: null,
					oidc_provider: null,
					targeted_audience_client_id: null,
					token_exchange: null,
				})
				expect(showSuccess).toHaveBeenCalledTimes(1)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.vm.loading).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.savedAuthMethod).toBe(AUTH_METHOD.OIDC)
				expect(wrapper.emitted().formcomplete).toHaveLength(1)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(true)
				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
				expect(wrapper.html()).toMatchSnapshot()
			})
			it('should show error on save failure', async () => {
				saveAdminConfig.mockImplementation(() => Promise.reject(new Error('Failure')))
				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				expect(wrapper.vm.savedAuthMethod).toBeNull()

				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OIDC
				await flushPromises()
				expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OIDC)

				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(saveAdminConfig).toHaveBeenCalledWith({
					authorization_method: AUTH_METHOD.OIDC,
					openproject_client_id: null,
					openproject_client_secret: null,
					sso_provider_type: null,
					oidc_provider: null,
					targeted_audience_client_id: null,
					token_exchange: null,
				})
				expect(showSuccess).toHaveBeenCalledTimes(0)
				expect(showError).toHaveBeenCalledTimes(1)
				expect(wrapper.vm.loading).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.vm.savedAuthMethod).toBeNull()
				expect(wrapper.emitted()).toEqual({})

				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.html()).toMatchSnapshot()
			})

		})

		describe('disabled user_oidc app', () => {
			beforeEach(async () => {
				const formState = JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM))
				formState.serverHost.complete = true
				wrapper = getWrapper({ props: { formState } })
			})
			it('should show disabled error message and disabled sso button', async () => {
				await wrapper.setProps({
					apps: {
						user_oidc: {
							enabled: false,
							supported: false,
							minimum_version: '1.0.0',
							name: 'OpenID Connect user backend',
						},
					},
				})

				expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('false')
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
				expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('true')

				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.html()).toMatchSnapshot()
			})
		})

		describe('unsupported user_oidc app', () => {
			beforeEach(async () => {
				const formState = JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM))
				formState.serverHost.complete = true
				wrapper = getWrapper({ props: { formState } })
			})
			it('should show disabled error message and disabled sso button', async () => {
				await wrapper.setProps({
					apps: {
						user_oidc: {
							enabled: true,
							supported: false,
							minimum_version: '1.0.0',
							name: 'OpenID Connect user backend',
						},
					},
				})

				expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('false')
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
				expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('true')

				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.html()).toMatchSnapshot()
			})
		})
	})

	describe('completed form', () => {
		let wrapper

		beforeEach(() => {
			const formState = JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM))
			formState.serverHost.complete = true
			wrapper = getWrapper({ props: { formState, authMethod: AUTH_METHOD.OIDC } })
		})

		it('should show set form label in view mode', () => {
			expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(true)
			expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
			expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)

			expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(false)
			expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(false)
			expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

			expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
			expect(wrapper.vm.selectedAuthMethod).toBe(AUTH_METHOD.OIDC)
			expect(wrapper.vm.savedAuthMethod).toBe(AUTH_METHOD.OIDC)
			expect(wrapper.html()).toMatchSnapshot()
		})

		describe('view mode: disabled user_oidc app', () => {
			it('should not show form errors if setup with oauth', async () => {
				wrapper = getWrapper({
					props: {
						authMethod: AUTH_METHOD.OAUTH2,
						apps: {
							user_oidc: {
								enabled: false,
								supported: false,
								minimum_version: '1.0.0',
								name: 'OpenID Connect user backend',
							},
						},
					},
				})
				expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('false')
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.editFormButton).attributes().disabled).toBe('false')
				expect(wrapper.html()).toMatchSnapshot()
			})
			it('should show form errors if setup with oidc', async () => {
				wrapper = getWrapper({
					props: {
						authMethod: AUTH_METHOD.OIDC,
						apps: {
							user_oidc: {
								enabled: false,
								supported: false,
								minimum_version: '1.0.0',
								name: 'OpenID Connect user backend',
							},
						},
					},
				})
				expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('true')
				const errorNote = wrapper.find(selectors.errorNote)
				expect(errorNote.exists()).toBe(true)
				expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported(app.userOidc.name, app.userOidc.minimum_version))
				expect(errorNote.attributes().errorlink).toBe(appLinks.user_oidc.installLink)
				expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.findComponent(selectors.editFormButton).attributes().disabled).toBe('false')
				expect(wrapper.html()).toMatchSnapshot()
			})
		})

		describe('edit mode', () => {
			beforeEach(async () => {
				const editButton = wrapper.findComponent(selectors.editFormButton)
				editButton.vm.$emit('click')
				await flushPromises()
			})

			it('should show form fields', async () => {
				expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OIDC)

				expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.html()).toMatchSnapshot()
			})
			it('should have disabled save button and enabled cancel button', async () => {
				expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
			})
			it('should disable save on same option', async () => {
				// change to OAUTH2
				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OAUTH2
				await flushPromises()
				expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('false')
				expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')

				// change back to OIDC
				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OIDC
				await flushPromises()
				expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
			})
			it('should show form in view mode on cancel', async () => {
				const cancelButton = wrapper.findComponent(selectors.cancelFormButton)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)

				// change to OIDC
				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OAUTH2
				await flushPromises()

				// save button should be enabled
				expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('false')
				expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')

				await cancelButton.vm.$emit('click')
				await flushPromises()

				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.selectedAuthMethod).toBe(AUTH_METHOD.OIDC)
				expect(wrapper.vm.savedAuthMethod).toBe(AUTH_METHOD.OIDC)
				expect(wrapper.find(selectors.formViewModeLabel).exists()).toBe(true)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
			})
			it('should show confirm dialog on save', async () => {
				// change to OAUTH2
				wrapper.vm.selectedAuthMethod = AUTH_METHOD.OAUTH2
				await flushPromises()
				expect(wrapper.vm.savedAuthMethod).toBe(AUTH_METHOD.OIDC)

				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(spyConfirmDialog).toBeCalledTimes(1)
			})

			describe('disabled user_oidc app', () => {
				it('should show error message and disabled sso button when oidc is selected', async () => {
					await wrapper.setProps({
						apps: {
							user_oidc: {
								enabled: false,
								supported: false,
								minimum_version: '1.0.0',
								name: 'OpenID Connect user backend',
							},
						},
					})

					expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('true')
					expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
					expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OIDC)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('false')
					const errorNote = wrapper.findComponent(selectors.errorNote)
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported(app.userOidc.name, app.userOidc.minimum_version))
					expect(errorNote.attributes().errorlink).toBe(appLinks.user_oidc.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)

					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
					expect(wrapper.html()).toMatchSnapshot()
				})
				it('should show disabled error message and disabled sso button when oauth2 is selected', async () => {
					wrapper = getWrapper({
						props: {
							authMethod: AUTH_METHOD.OAUTH2,
							apps: {
								user_oidc: {
									enabled: false,
									supported: false,
									minimum_version: '1.0.0',
									name: 'OpenID Connect user backend',
								},
							},
						},
					})
					const editButton = wrapper.findComponent(selectors.editFormButton)
					editButton.vm.$emit('click')
					await flushPromises()

					expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('false')
					expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
					expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OAUTH2)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
					expect(wrapper.html()).toMatchSnapshot()
				})
			})

			describe('unsupported user_oidc app', () => {
				it('should show error message and disabled sso button when oidc is selected', async () => {
					await wrapper.setProps({
						apps: {
							user_oidc: {
								enabled: true,
								supported: false,
								minimum_version: '1.0.0',
								name: 'OpenID Connect user backend',
							},
						},
					})

					expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('true')
					expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
					expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OIDC)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('false')
					const errorNote = wrapper.findComponent(selectors.errorNote)
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported(app.userOidc.name, app.userOidc.minimum_version))
					expect(errorNote.attributes().errorlink).toBe(appLinks.user_oidc.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)

					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
					expect(wrapper.html()).toMatchSnapshot()
				})
				it('should show disabled error message and disabled sso button when oauth2 is selected', async () => {
					wrapper = getWrapper({
						props: {
							authMethod: AUTH_METHOD.OAUTH2,
							apps: {
								user_oidc: {
									enabled: true,
									supported: false,
									minimum_version: '1.0.0',
									name: 'OpenID Connect user backend',
								},
							},
						},
					})
					const editButton = wrapper.findComponent(selectors.editFormButton)
					editButton.vm.$emit('click')
					await flushPromises()

					expect(wrapper.findComponent(selectors.formheading).attributes().haserror).toBe('false')
					expect(wrapper.find(selectors.oauthRadioBox).exists()).toBe(true)
					expect(wrapper.find(selectors.ssoRadioBox).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.ssoRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.oauthRadioBox).attributes().modelvalue).toBe(AUTH_METHOD.OAUTH2)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.errorLabel).attributes().disabled).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
					expect(wrapper.findComponent(selectors.saveFormButton).attributes().disabled).toBe('true')
					expect(wrapper.findComponent(selectors.cancelFormButton).attributes().disabled).toBe('false')
					expect(wrapper.html()).toMatchSnapshot()
				})
			})
		})
	})
})

function getWrapper({ data = {}, props = {} } = {}) {
	return shallowMount(FormAuthMethod, {
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
