/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { ADMIN_SETTINGS_FORM, F_MODES, SSO_PROVIDER_TYPE, SSO_PROVIDER_LABEL } from '../../../../src/utils.js'
import { saveAdminConfig } from '../../../../src/api/settings.js'
import FormSSOSettings from '../../../../src/components/admin/FormSSOSettings.vue'
import { messagesFmt, messages } from '../../../../src/constants/messages.js'
import { appLinks } from '../../../../src/constants/links.js'

// global mocks
global.t = (app, text) => text
global.OC = {
	dialogs: {
		confirmDestructive: jest.fn(),
		YES_NO_BUTTONS: 70,
	},
}
// module mocks
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))
jest.mock('../../../../src/api/settings.js', () => ({
	saveAdminConfig: jest.fn(() => ''),
}))

const localVue = createLocalVue()

const selectors = {
	formHeading: 'formheading-stub',
	providerSelect: '.sso-provider ncselect-stub',
	clientIdInput: '.sso-client-id textinput-stub',
	ssoNextcloudRadioBox: `nccheckboxradioswitch-stub[value="${SSO_PROVIDER_TYPE.nextcloudHub}"]`,
	ssoExternalRadioBox: `nccheckboxradioswitch-stub[value="${SSO_PROVIDER_TYPE.external}"]`,
	tokenExchangeSwitch: '.sso-token-exchange nccheckboxradioswitch-stub',
	saveFormButton: '[data-test-id="save-sso-settings"]',
	editFormButton: '[data-test-id="edit-sso-settings"]',
	cancelFormButton: '[data-test-id="cancel-sso-settings-edit"]',
	errorLabel: 'errorlabel-stub',
	errorNote: 'errornote-stub',
	fieldValue: 'fieldvalue-stub',
}

const appsState = {
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
}

const formState = JSON.parse(JSON.stringify(ADMIN_SETTINGS_FORM))
formState.serverHost.complete = true
formState.authenticationMethod.complete = true
const defaultProps = {
	formState,
	apps: appsState,
	ssoSettings: {
		sso_provider_type: '',
		oidc_provider: '',
		targeted_audience_client_id: '',
		token_exchange: '',
	},
	ssoProviders: ['keycloak'],
}

describe('Component: FormSSOSettings', () => {
	afterEach(() => {
		jest.clearAllMocks()
	})

	describe('new form: edit mode', () => {
		let wrapper

		describe('with supported apps enabled', () => {
			beforeEach(async () => {
				wrapper = getWrapper({ props: defaultProps })
			})

			it('should hide form fields when preceding form is not complete', () => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.formState.authenticationMethod.complete = false
				wrapper = getWrapper({ props })
				const formHeading = wrapper.find(selectors.formHeading)
				expect(formHeading.attributes().isdisabled).toBe('true')
				expect(formHeading.attributes().haserror).toBe(undefined)
				expect(formHeading.attributes().iscomplete).toBe(undefined)
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(false)
				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				toMatchSerializedSnapshot(wrapper.html())
			})

			it('should show form fields without errors', () => {
				expect(wrapper.vm.formMode).toBe(F_MODES.NEW)
				expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe(undefined)
				expect(wrapper.find(selectors.formHeading).attributes().disabled).toBe(undefined)
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

				expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().value).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(true)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().value).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)

				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(true)
				expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should show "Save" button disabled', () => {
				const saveFormButton = wrapper.find(selectors.saveFormButton)
				expect(saveFormButton.attributes().disabled).toBe('true')
			})
			it('should not show "Cancel" button', () => {
				const cancelFormButton = wrapper.find(selectors.cancelFormButton)
				expect(cancelFormButton.exists()).toBe(false)
			})
			it('should disable "Save" button if client-id is empty', () => {
				const saveFormButton = wrapper.find(selectors.saveFormButton)
				expect(saveFormButton.attributes().disabled).toBe('true')
			})

			it('should enable "Save" button if the form is complete', async () => {
				expect(wrapper.vm.formMode).toBe(F_MODES.NEW)
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')

				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id')
				await localVue.nextTick()

				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
				expect(clientIdInput.attributes().value).toBe('op-client-id')
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should show form related to selected provider type', async () => {
				const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
				ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
				await localVue.nextTick()

				expect(wrapper.find(selectors.providerSelect).exists()).toBe(true)
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(true)
				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.vm.currentForm.oidc_provider).toBe(null)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				toMatchSerializedSnapshot(wrapper.html())

				const ssoNCRadioBox = wrapper.find(selectors.ssoNextcloudRadioBox)
				ssoNCRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.nextcloudHub)
				await localVue.nextTick()

				expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(false)
				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.currentForm.oidc_provider).toBe(null)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				toMatchSerializedSnapshot(wrapper.html())
			})

			describe('external SSO provider', () => {
				beforeEach(async () => {
					wrapper = getWrapper({ props: defaultProps })
					const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
					ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
					await localVue.nextTick()
				})

				it('should not disable form elements', () => {
					const providerSelectInput = wrapper.find(selectors.providerSelect)
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)

					expect(providerSelectInput.attributes().disabled).toBe(undefined)
					expect(tokenExchangeSwitch.exists()).toBe(true)
					expect(clientIdInput.exists()).toBe(false)
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				})
				it('should show "Save" button disabled', () => {
					const saveFormButton = wrapper.find(selectors.saveFormButton)
					expect(saveFormButton.attributes().disabled).toBe('true')
				})
				it('should not show "Cancel" button', () => {
					const cancelFormButton = wrapper.find(selectors.cancelFormButton)
					expect(cancelFormButton.exists()).toBe(false)
				})
				it('should enable "Save" button if the form is complete', async () => {
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')

					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'keycloak')
					await localVue.nextTick()

					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
					expect(providerSelect.attributes().value).toBe('keycloak')
					expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
					toMatchSerializedSnapshot(wrapper.html())
				})

				describe('when token change is enabled', () => {
					beforeEach(async () => {
						const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
						await tokenExchangeSwitch.vm.$emit('update:checked', true)
						await localVue.nextTick()
					})
					it('should show client-id field', async () => {
						expect(wrapper.find(selectors.clientIdInput).exists()).toBe(true)
						expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should disable "Save" button', async () => {
						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe('true')
					})
					it('should enable "Save" button if the form is complete', async () => {
						const providerSelect = wrapper.find(selectors.providerSelect)
						await providerSelect.vm.$emit('option:selected', 'keycloak')
						const clientIdInput = wrapper.find(selectors.clientIdInput)
						await clientIdInput.vm.$emit('input', 'op-client-id')
						await localVue.nextTick()

						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe(undefined)
						expect(providerSelect.attributes().value).toBe('keycloak')
						expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
						expect(clientIdInput.attributes().value).toBe('op-client-id')
						expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should disable "Save" button if the provider is not selected', async () => {
						const providerSelect = wrapper.find(selectors.providerSelect)
						const clientIdInput = wrapper.find(selectors.clientIdInput)
						await clientIdInput.vm.$emit('input', 'op-client-id')
						await localVue.nextTick()

						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe('true')
						expect(providerSelect.attributes().value).toBe(undefined)
						expect(wrapper.vm.currentForm.oidc_provider).toBe(null)
						expect(clientIdInput.attributes().value).toBe('op-client-id')
						expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
					})
					it('should disable "Save" button if the client-id is not provided', async () => {
						const providerSelect = wrapper.find(selectors.providerSelect)
						await providerSelect.vm.$emit('option:selected', 'keycloak')
						const clientIdInput = wrapper.find(selectors.clientIdInput)
						await localVue.nextTick()

						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe('true')
						expect(providerSelect.attributes().value).toBe('keycloak')
						expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
						expect(clientIdInput.attributes().value).toBe('')
						expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('')
					})
				})
			})

			describe('save button', () => {
				describe('Nextcloud Hub', () => {
					beforeEach(async () => {
						jest.clearAllMocks()
						const clientIdInput = wrapper.find(selectors.clientIdInput)
						await clientIdInput.vm.$emit('input', 'op-client-id')
						await localVue.nextTick()
					})
					it('should set sso settings on save', async () => {
						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe(undefined)
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('')
						await saveFormButton.vm.$emit('click')
						await localVue.nextTick()

						expect(saveAdminConfig).toBeCalledTimes(1)
						expect(saveAdminConfig).toBeCalledWith({
							sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
							oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
							targeted_audience_client_id: 'op-client-id',
							token_exchange: null,
						})
						expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
						expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
						expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
						expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
						expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
						expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
						expect(wrapper.vm.savedForm.token_exchange).toBe(null)
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
						expect(wrapper.vm.loading).toBe(false)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
						toMatchSerializedSnapshot(wrapper.html())
					})
				})

				describe('external SSO Provider', () => {
					beforeEach(async () => {
						jest.clearAllMocks()
						const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
						await ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
						const providerSelect = wrapper.find(selectors.providerSelect)
						await providerSelect.vm.$emit('option:selected', 'keycloak')
						await localVue.nextTick()
					})
					it('should set sso settings on save: without token exchange', async () => {
						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe(undefined)
						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
						expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
						expect(wrapper.vm.savedForm.token_exchange).toBe('')
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('')
						await saveFormButton.vm.$emit('click')
						await localVue.nextTick()

						expect(saveAdminConfig).toBeCalledTimes(1)
						expect(saveAdminConfig).toBeCalledWith({
							sso_provider_type: SSO_PROVIDER_TYPE.external,
							oidc_provider: 'keycloak',
							targeted_audience_client_id: null,
							token_exchange: '',
						})
						expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
						expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
						expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
						expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
						expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
						expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
						expect(wrapper.vm.savedForm.token_exchange).toBe('')
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe(null)
						expect(wrapper.vm.loading).toBe(false)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(3)
						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should set sso settings on save: with token exchange', async () => {
						const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
						await tokenExchangeSwitch.vm.$emit('update:checked', true)
						const clientIdInput = wrapper.find(selectors.clientIdInput)
						await clientIdInput.vm.$emit('input', 'op-client-id')
						await localVue.nextTick()
						const saveFormButton = wrapper.find(selectors.saveFormButton)
						expect(saveFormButton.attributes().disabled).toBe(undefined)
						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
						expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('')
						expect(wrapper.vm.savedForm.token_exchange).toBe('')
						await saveFormButton.vm.$emit('click')
						await localVue.nextTick()

						expect(saveAdminConfig).toBeCalledTimes(1)
						expect(saveAdminConfig).toBeCalledWith({
							sso_provider_type: SSO_PROVIDER_TYPE.external,
							oidc_provider: 'keycloak',
							targeted_audience_client_id: 'op-client-id',
							token_exchange: true,
						})
						expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
						expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
						expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
						expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
						expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
						expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
						expect(wrapper.vm.savedForm.token_exchange).toBe(true)
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
						expect(wrapper.vm.loading).toBe(false)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(4)
						toMatchSerializedSnapshot(wrapper.html())
					})
				})
			})
		})

		describe('apps state', () => {
			describe.each([
				['disabled user_oidc app', { enabled: false, supported: true }],
				['unsupported user_oidc app', { enabled: true, supported: false }],
			])('%s', (_, state) => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.apps.user_oidc.enabled = state.enabled
				props.apps.user_oidc.supported = state.supported
				beforeEach(async () => {
					wrapper = getWrapper({ props })
				})

				it('should not show error card when preceding form is not complete', () => {
					const updatedProps = JSON.parse(JSON.stringify(props))
					updatedProps.formState.authenticationMethod.complete = false
					wrapper = getWrapper({ props: updatedProps })

					const formHeading = wrapper.find(selectors.formHeading)

					expect(formHeading.attributes().isdisabled).toBe('true')
					expect(formHeading.attributes().haserror).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show error card with disabled form fields', () => {
					const formHeading = wrapper.find(selectors.formHeading)
					const errorNote = wrapper.find(selectors.errorNote)

					expect(formHeading.attributes().haserror).toBe('true')
					expect(formHeading.attributes().isdisabled).toBe(undefined)
					expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported())
					expect(errorNote.attributes().errorlink).toBe(appLinks.user_oidc.installLink)

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.find(selectors.clientIdInput).attributes().disabled).toBe('true')
					toMatchSerializedSnapshot(wrapper.html())
				})
			})

			describe.each([
				['disabled oidc app', { enabled: false, supported: true }],
				['unsupported oidc app', { enabled: true, supported: false }],
			])('%s', (_, state) => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.apps.oidc.enabled = state.enabled
				props.apps.oidc.supported = state.supported
				beforeEach(async () => {
					wrapper = getWrapper({ props })
				})

				it('should not show error card when preceding form is not complete', () => {
					const updatedProps = JSON.parse(JSON.stringify(props))
					updatedProps.formState.authenticationMethod.complete = false
					wrapper = getWrapper({ props: updatedProps })

					const formHeading = wrapper.find(selectors.formHeading)

					expect(formHeading.attributes().isdisabled).toBe('true')
					expect(formHeading.attributes().haserror).toBe(undefined)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show disabled error label but not error card', () => {
					const formHeading = wrapper.find(selectors.formHeading)
					expect(formHeading.attributes().isdisabled).toBe(undefined)
					expect(formHeading.attributes().haserror).toBe(undefined)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

					const errorLabel = wrapper.find(selectors.errorLabel)
					expect(errorLabel.attributes().error).toBe(messagesFmt.appNotEnabledOrUnsupported())
					expect(errorLabel.attributes().disabled).toBe('true')

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().disabled).toBe('true')
					expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().disabled).toBe(undefined)
					expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.find(selectors.providerSelect).exists()).toBe(true)
					expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(true)
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
					toMatchSerializedSnapshot(wrapper.html())
				})
			})
		})
	})

	describe('partially complete form', (settings) => {
		it.each([
			['Nextcloud Hub', {
				sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
				oidc_provider: '',
				token_exchange: '',
				targeted_audience_client_id: '',
			}],
			['external without provider', {
				sso_provider_type: SSO_PROVIDER_TYPE.external,
				oidc_provider: '',
				token_exchange: false,
				targeted_audience_client_id: '',
			}],
			['external token exchange and without provider', {
				sso_provider_type: SSO_PROVIDER_TYPE.external,
				oidc_provider: '',
				token_exchange: true,
				targeted_audience_client_id: 'client-id',
			}],
			['external token exchange and without client-id', {
				sso_provider_type: SSO_PROVIDER_TYPE.external,
				oidc_provider: 'keycloak',
				token_exchange: true,
				targeted_audience_client_id: '',
			}],
		])('%s - should show form fields', (_, settings) => {
			const props = JSON.parse(JSON.stringify(defaultProps))
			props.ssoSettings = settings
			const wrapper = getWrapper({ props })

			expect(wrapper.vm.formMode).toBe(F_MODES.NEW)
			expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
			expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)

			if (settings.sso_provider_type === SSO_PROVIDER_TYPE.nextcloudHub) {
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.clientIdInput).attributes().value).toBe(settings.targeted_audience_client_id)
				expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(false)
			} else {
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.find(selectors.providerSelect).attributes().value).toBe(settings.oidc_provider)
				if (settings.token_exchange) {
					expect(wrapper.find(selectors.clientIdInput).attributes().value).toBe(settings.targeted_audience_client_id)
					expect(wrapper.find(selectors.tokenExchangeSwitch).attributes().checked).toBe(`${settings.token_exchange}`)
				} else {
					expect(wrapper.find(selectors.tokenExchangeSwitch).attributes().checked).toBe(undefined)
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
				}
			}
			toMatchSerializedSnapshot(wrapper.html())
		})

	})

	describe('complete form: view mode', () => {
		let wrapper

		describe('with supported apps enabled', () => {
			it.each([
				[
					'complete Nextcloud Hub',
					{
						sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
						oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
						token_exchange: '',
						targeted_audience_client_id: 'op-client-id',
					},
					2,
					SSO_PROVIDER_TYPE.nextcloudHub,
				],
				[
					'complete external provider without token exchange',
					{
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'some-oidc-provider',
						token_exchange: false,
						targeted_audience_client_id: '',
					},
					3,
					SSO_PROVIDER_TYPE.external,
				],
				[
					'complete external provider with token exchange',
					 {
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'some-oidc-provider',
						token_exchange: true,
						targeted_audience_client_id: 'op-client-id',
					},
					4,
					SSO_PROVIDER_TYPE.external,
				],
			])('should show the settings in view mode - %s', (_, settings, fieldsLength, providerType) => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.ssoSettings = settings
				wrapper = getWrapper({ props })

				const formHeading = wrapper.find(selectors.formHeading)
				expect(formHeading.attributes().haserror).toBe(undefined)
				expect(formHeading.attributes().disabled).toBe(undefined)
				expect(wrapper.find(selectors.errorNote).exists()).toBe(false)

				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(settings.sso_provider_type)
				expect(wrapper.vm.currentForm.oidc_provider).toBe(settings.oidc_provider)
				expect(wrapper.vm.currentForm.token_exchange).toBe(settings.token_exchange)
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe(settings.targeted_audience_client_id)

				const formFields = wrapper.findAll(selectors.fieldValue)

				expect(formFields).toHaveLength(fieldsLength)
				expect(formFields.at(0).attributes().value).toBe(settings.sso_provider_type)
				if (providerType === SSO_PROVIDER_TYPE.nextcloudHub) {
					expect(formFields.at(1).attributes().value).toBe(settings.targeted_audience_client_id)
				} else {
					expect(formFields.at(1).attributes().value).toBe(settings.oidc_provider)
					expect(formFields.at(2).attributes().value).toBe(`${settings.token_exchange}`)
					fieldsLength === 4 && expect(formFields.at(3).attributes().value).toBe(settings.targeted_audience_client_id)
				}

				expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
				expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				toMatchSerializedSnapshot(wrapper.html())
			})
		})

		describe('apps state', () => {
			describe.each([
				[
					'disabled user_oidc app',
					{ enabled: false, supported: true },
					{
						sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
						oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
						token_exchange: '',
						targeted_audience_client_id: 'op-client-id',
					},
					2,
				],
				[
					'unsupported user_oidc app',
					{ enabled: true, supported: false },
					{
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'some-oidc-provider',
						token_exchange: false,
						targeted_audience_client_id: '',
					},
					3,
				],
			])('%s', (_, state, settings, fieldsLength) => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.ssoSettings = settings
				props.apps.user_oidc.enabled = state.enabled
				props.apps.user_oidc.supported = state.supported
				beforeEach(async () => {
					wrapper = getWrapper({ props })
				})

				it('should show error card', () => {
					const errorNote = wrapper.find(selectors.errorNote)
					expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
					expect(errorNote.exists()).toBe(true)
					expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported('user_oidc'))
					expect(errorNote.attributes().errorlink).toBe(appLinks.user_oidc.installLink)
					expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
					expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe('true')

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
					expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should show saved settings', () => {
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(fieldsLength)
				})
				it('should disable "Edit" button', () => {
					expect(wrapper.find(selectors.editFormButton).attributes().disabled).toBe('true')
				})
			})

			describe('oidc app', () => {
				describe.each([
					[
						'disabled app',
						{ enabled: false, supported: true },
						{
							sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
							oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
							token_exchange: '',
							targeted_audience_client_id: 'op-client-id',
						},
					],
					[
						'unsupported app',
						{ enabled: false, supported: true },
						{
							sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
							oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
							token_exchange: '',
							targeted_audience_client_id: 'op-client-id',
						},
					],
				])('%s - Nextcloud Hub settings', (_, state, settings) => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = settings
					props.apps.oidc.enabled = state.enabled
					props.apps.oidc.supported = state.supported
					beforeEach(async () => {
						wrapper = getWrapper({ props })
					})

					it('should show error card', () => {
						const errorNote = wrapper.find(selectors.errorNote)
						expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
						expect(errorNote.exists()).toBe(true)
						expect(errorNote.attributes().errortitle).toBe(messagesFmt.appNotEnabledOrUnsupported('oidc'))
						expect(errorNote.attributes().errorlink).toBe(appLinks.oidc.installLink)
						expect(errorNote.attributes().errorlinklabel).toBe(messages.installLatestVersionNow)
						expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe('true')

						expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
						expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
						expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
						expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should show saved settings', () => {
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
					})
					it('should show "Edit" button', () => {
						expect(wrapper.find(selectors.editFormButton).attributes().disabled).toBe(undefined)
					})
				})

				describe.each([
					[
						'disabled app',
						{ enabled: false, supported: true },
						{
							sso_provider_type: SSO_PROVIDER_TYPE.external,
							oidc_provider: 'some-oidc-provider',
							token_exchange: false,
							targeted_audience_client_id: '',
						},
						3,
					],
					[
						'unsupported app',
						{ enabled: true, supported: false },
						{
							sso_provider_type: SSO_PROVIDER_TYPE.external,
							oidc_provider: 'some-oidc-provider',
							token_exchange: true,
							targeted_audience_client_id: 'op-client-id',
						},
						4,
					],
				])('%s - external provider settings', (_, state, settings, fieldsLength) => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = settings
					props.apps.oidc.enabled = state.enabled
					props.apps.oidc.supported = state.supported
					beforeEach(async () => {
						wrapper = getWrapper({ props })
					})

					it('should not show error card', () => {
						expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
						expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe(undefined)

						expect(wrapper.find(selectors.ssoNextcloudRadioBox).exists()).toBe(false)
						expect(wrapper.find(selectors.ssoExternalRadioBox).exists()).toBe(false)
						expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
						expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
						toMatchSerializedSnapshot(wrapper.html())
					})
					it('should show saved settings', () => {
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(fieldsLength)
					})
					it('should show "Edit" button', () => {
						expect(wrapper.find(selectors.editFormButton).attributes().disabled).toBe(undefined)
					})
				})
			})
		})
	})

	describe('complete form: edit mode', () => {
		let wrapper

		describe('Nextcloud Hub', () => {
			beforeEach(async () => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.ssoSettings = {
					sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
					oidc_provider: '',
					token_exchange: '',
					targeted_audience_client_id: 'op-client-id',
				}
				wrapper = getWrapper({ props })
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				const editFormButton = wrapper.find(selectors.editFormButton)
				editFormButton.vm.$emit('click')
				await localVue.nextTick()
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
			})

			it('should show the form fields', () => {
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.find(selectors.clientIdInput).attributes().value).toBe('op-client-id')
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should show the action buttons', () => {
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
			})
			it('should enable "save" button if client-id is changed', async () => {
				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id-new')
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
				// disabled save button on old client id
				await clientIdInput.vm.$emit('input', 'op-client-id')
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should reset the changes on cancel', async () => {
				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id-new')
				const cancelFormButton = wrapper.find(selectors.cancelFormButton)
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
				await cancelFormButton.vm.$emit('click')
				await localVue.nextTick()

				expect(saveAdminConfig).toBeCalledTimes(0)
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

				expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.savedForm.token_exchange).toBe('')
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.currentForm.token_exchange).toBe('')
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
				expect(showSuccess).toHaveBeenCalledTimes(0)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should set sso settings on save', async () => {
				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id-new')
				const saveFormButton = wrapper.find(selectors.saveFormButton)
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
				await saveFormButton.vm.$emit('click')
				await localVue.nextTick()

				expect(saveAdminConfig).toBeCalledTimes(1)
				expect(saveAdminConfig).toBeCalledWith({
					sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
					oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
					targeted_audience_client_id: 'op-client-id-new',
					token_exchange: null,
				})
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

				expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
				expect(wrapper.vm.savedForm.token_exchange).toBe(null)
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id-new')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
				expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
				expect(wrapper.vm.currentForm.token_exchange).toBe(null)
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id-new')
				expect(wrapper.vm.loading).toBe(false)
				expect(showSuccess).toHaveBeenCalledTimes(1)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
				toMatchSerializedSnapshot(wrapper.html())
			})

			describe('change to external provider', () => {
				beforeEach(async () => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = {
						sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
						oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
						token_exchange: '',
						targeted_audience_client_id: 'op-client-id',
					}
					wrapper = getWrapper({ props })
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					const editFormButton = wrapper.find(selectors.editFormButton)
					editFormButton.vm.$emit('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
					expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
					const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
					ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
					await localVue.nextTick()
				})

				it('should show external provider form fields', async () => {
					expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
					expect(wrapper.find(selectors.providerSelect).exists()).toBe(true)
					expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(true)
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')

					expect(wrapper.vm.currentForm.oidc_provider).toBe(null)

					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', true)
					await localVue.nextTick()
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(true)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should reset the changes on cancel', async () => {
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'keycloak')
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', true)
					await localVue.nextTick()
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id-new')
					await localVue.nextTick()
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)

					const cancelFormButton = wrapper.find(selectors.cancelFormButton)
					await cancelFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(0)
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
					expect(wrapper.vm.savedForm.token_exchange).toBe('')
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
					expect(wrapper.vm.currentForm.token_exchange).toBe('')
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
					expect(showSuccess).toHaveBeenCalledTimes(0)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should set settings on save', async () => {
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'keycloak')
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', true)
					await localVue.nextTick()
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id-new')
					await localVue.nextTick()
					const saveFormButton = wrapper.find(selectors.saveFormButton)
					await saveFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(1)
					expect(saveAdminConfig).toBeCalledWith({
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'keycloak',
						targeted_audience_client_id: 'op-client-id-new',
						token_exchange: true,
					})
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.savedForm.token_exchange).toBe(true)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id-new')
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.currentForm.token_exchange).toBe(true)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id-new')
					expect(showSuccess).toHaveBeenCalledTimes(1)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(4)
					toMatchSerializedSnapshot(wrapper.html())
				})
			})

			describe('disabled oidc app', () => {
				beforeEach(async () => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = {
						sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
						oidc_provider: '',
						token_exchange: '',
						targeted_audience_client_id: 'op-client-id',
					}
					props.apps.oidc.enabled = false
					wrapper = getWrapper({ props })
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					const editFormButton = wrapper.find(selectors.editFormButton)
					editFormButton.vm.$emit('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
				})

				it('should be able to select external provider', async () => {
					const ssoNextcloudRadioBox = wrapper.find(selectors.ssoNextcloudRadioBox)
					expect(ssoNextcloudRadioBox.attributes().disabled).toBe('true')
					expect(ssoNextcloudRadioBox.attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
					expect(ssoExternalRadioBox.attributes().disabled).toBe(undefined)
					expect(ssoExternalRadioBox.attributes().checked).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
					await localVue.nextTick()

					expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(true)
					expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.find(selectors.errorLabel).attributes().disabled).toBe(undefined)
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)

					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'keycloak')
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', true)
					await localVue.nextTick()
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id-new')
					await localVue.nextTick()
					const saveFormButton = wrapper.find(selectors.saveFormButton)
					await saveFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(1)
					expect(saveAdminConfig).toBeCalledWith({
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'keycloak',
						targeted_audience_client_id: 'op-client-id-new',
						token_exchange: true,
					})
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(false)
					expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe(undefined)

					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.savedForm.token_exchange).toBe(true)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id-new')
					expect(wrapper.vm.loading).toBe(false)
					expect(showSuccess).toHaveBeenCalledTimes(1)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(4)
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should preserve the errors on cancel', async () => {
					const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
					ssoExternalRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.external)
					await localVue.nextTick()

					expect(wrapper.find(selectors.errorNote).exists()).toBe(true)
					expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.find(selectors.errorLabel).attributes().disabled).toBe(undefined)
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)

					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'keycloak')
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', true)
					await localVue.nextTick()
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id-new')
					await localVue.nextTick()
					const cancelFormButton = wrapper.find(selectors.cancelFormButton)
					await cancelFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(0)
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.errorNote).exists()).toBe(true)
					expect(wrapper.findAll(selectors.errorNote)).toHaveLength(1)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(false)
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('')
					expect(wrapper.vm.savedForm.token_exchange).toBe('')
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
					expect(showSuccess).toHaveBeenCalledTimes(0)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
					toMatchSerializedSnapshot(wrapper.html())
				})
			})
		})

		describe('external provider', () => {
			beforeEach(async () => {
				const props = JSON.parse(JSON.stringify(defaultProps))
				props.ssoSettings = {
					sso_provider_type: SSO_PROVIDER_TYPE.external,
					oidc_provider: 'keycloak',
					token_exchange: false,
					targeted_audience_client_id: '',
				}
				wrapper = getWrapper({ props })
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				const editFormButton = wrapper.find(selectors.editFormButton)
				editFormButton.vm.$emit('click')
				await localVue.nextTick()
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
			})

			it('should show the form fields', () => {
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.find(selectors.providerSelect).attributes().value).toBe('keycloak')
				expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(true)
				expect(wrapper.find(selectors.clientIdInput).exists()).toBe(false)
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should show the action buttons', () => {
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
			})
			it('should enable "save" button if the settings changed', async () => {
				// change provider
				const providerSelect = wrapper.find(selectors.providerSelect)
				await providerSelect.vm.$emit('option:selected', 'new-provider')
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
				// revert
				await providerSelect.vm.$emit('option:selected', 'keycloak')
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')

				// enable token exchange
				const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
				tokenExchangeSwitch.vm.$emit('update:checked', true)
				await localVue.nextTick()
				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id-new')
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
				// revert
				tokenExchangeSwitch.vm.$emit('update:checked', false)
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
			})
			it('should reset the changes on cancel', async () => {
				const providerSelect = wrapper.find(selectors.providerSelect)
				await providerSelect.vm.$emit('option:selected', 'new-provider')
				const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
				tokenExchangeSwitch.vm.$emit('update:checked', true)
				await localVue.nextTick()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')

				const cancelFormButton = wrapper.find(selectors.cancelFormButton)
				await cancelFormButton.vm.$emit('click')
				await localVue.nextTick()

				expect(saveAdminConfig).toBeCalledTimes(0)
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

				expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
				expect(wrapper.vm.savedForm.token_exchange).toBe(false)
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
				expect(wrapper.vm.currentForm.token_exchange).toBe(false)
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('')
				expect(showSuccess).toHaveBeenCalledTimes(0)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(3)
				toMatchSerializedSnapshot(wrapper.html())
			})
			it('should set sso settings on save', async () => {
				const providerSelect = wrapper.find(selectors.providerSelect)
				await providerSelect.vm.$emit('option:selected', 'new-provider')
				const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
				tokenExchangeSwitch.vm.$emit('update:checked', true)
				await localVue.nextTick()
				const clientIdInput = wrapper.find(selectors.clientIdInput)
				await clientIdInput.vm.$emit('input', 'op-client-id')
				const saveFormButton = wrapper.find(selectors.saveFormButton)
				await saveFormButton.vm.$emit('click')
				await localVue.nextTick()

				expect(saveAdminConfig).toBeCalledTimes(1)
				expect(saveAdminConfig).toBeCalledWith({
					sso_provider_type: SSO_PROVIDER_TYPE.external,
					oidc_provider: 'new-provider',
					targeted_audience_client_id: 'op-client-id',
					token_exchange: true,
				})
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

				expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.vm.savedForm.oidc_provider).toBe('new-provider')
				expect(wrapper.vm.savedForm.token_exchange).toBe(true)
				expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
				expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
				expect(wrapper.vm.currentForm.oidc_provider).toBe('new-provider')
				expect(wrapper.vm.currentForm.token_exchange).toBe(true)
				expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
				expect(wrapper.vm.loading).toBe(false)
				expect(showSuccess).toHaveBeenCalledTimes(1)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(4)
				toMatchSerializedSnapshot(wrapper.html())
			})

			describe('change to Nextcloud Hub', () => {
				beforeEach(async () => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = {
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'keycloak',
						token_exchange: false,
						targeted_audience_client_id: '',
					}
					wrapper = getWrapper({ props })
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					const editFormButton = wrapper.find(selectors.editFormButton)
					editFormButton.vm.$emit('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
					const ssoNextcloudRadioBox = wrapper.find(selectors.ssoNextcloudRadioBox)
					ssoNextcloudRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.nextcloudHub)
					await localVue.nextTick()
				})

				it('should show form fields', async () => {
					expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
					expect(wrapper.find(selectors.providerSelect).exists()).toBe(false)
					expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(false)
					expect(wrapper.find(selectors.clientIdInput).exists()).toBe(true)
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should reset the changes on cancel', async () => {
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id')
					await localVue.nextTick()
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)

					const cancelFormButton = wrapper.find(selectors.cancelFormButton)
					await cancelFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(0)
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.savedForm.token_exchange).toBe(false)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('')
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.currentForm.token_exchange).toBe(false)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('')
					expect(showSuccess).toHaveBeenCalledTimes(0)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(3)
				})
				it('should set settings on save', async () => {
					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					const clientIdInput = wrapper.find(selectors.clientIdInput)
					await clientIdInput.vm.$emit('input', 'op-client-id')
					await localVue.nextTick()

					const saveFormButton = wrapper.find(selectors.saveFormButton)
					await saveFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(1)
					expect(saveAdminConfig).toBeCalledWith({
						sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
						oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
						targeted_audience_client_id: 'op-client-id',
						token_exchange: null,
					})
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
					expect(wrapper.vm.savedForm.token_exchange).toBe(null)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
					expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
					expect(wrapper.vm.currentForm.token_exchange).toBe(null)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
					expect(wrapper.vm.loading).toBe(false)
					expect(showSuccess).toHaveBeenCalledTimes(1)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
				})
			})

			describe('disabled oidc app', () => {
				beforeEach(async () => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = {
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'keycloak',
						token_exchange: false,
						targeted_audience_client_id: '',
					}
					props.apps.oidc.enabled = false
					wrapper = getWrapper({ props })
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					const editFormButton = wrapper.find(selectors.editFormButton)
					editFormButton.vm.$emit('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
				})

				it('should not show error card', async () => {
					const ssoNextcloudRadioBox = wrapper.find(selectors.ssoNextcloudRadioBox)
					expect(ssoNextcloudRadioBox.attributes().disabled).toBe('true')
					expect(ssoNextcloudRadioBox.attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
					const ssoExternalRadioBox = wrapper.find(selectors.ssoExternalRadioBox)
					expect(ssoExternalRadioBox.attributes().disabled).toBe(undefined)
					expect(ssoExternalRadioBox.attributes().checked).toBe(SSO_PROVIDER_TYPE.external)

					expect(wrapper.find(selectors.formHeading).attributes().haserror).toBe(undefined)
					expect(wrapper.find(selectors.errorNote).exists()).toBe(false)
					expect(wrapper.find(selectors.errorLabel).exists()).toBe(true)
					expect(wrapper.find(selectors.errorLabel).attributes().disabled).toBe('true')
					toMatchSerializedSnapshot(wrapper.html())
				})
			})

			describe('token exchange enabled', () => {
				beforeEach(async () => {
					const props = JSON.parse(JSON.stringify(defaultProps))
					props.ssoSettings = {
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'keycloak',
						token_exchange: true,
						targeted_audience_client_id: 'op-client-id',
					}
					wrapper = getWrapper({ props })
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					const editFormButton = wrapper.find(selectors.editFormButton)
					editFormButton.vm.$emit('click')
					await localVue.nextTick()
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe(undefined)
				})

				it('should show the form fields', () => {
					expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
					expect(wrapper.find(selectors.ssoNextcloudRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.find(selectors.ssoExternalRadioBox).attributes().checked).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.find(selectors.providerSelect).attributes().value).toBe('keycloak')
					expect(wrapper.find(selectors.tokenExchangeSwitch).exists()).toBe(true)
					expect(wrapper.find(selectors.clientIdInput).attributes().value).toBe('op-client-id')

					expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
					toMatchSerializedSnapshot(wrapper.html())
				})
				it('should reset the changes on cancel', async () => {
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', false)
					await localVue.nextTick()
					expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)

					const cancelFormButton = wrapper.find(selectors.cancelFormButton)
					await cancelFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(0)
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.savedForm.token_exchange).toBe(true)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.currentForm.oidc_provider).toBe('keycloak')
					expect(wrapper.vm.currentForm.token_exchange).toBe(true)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
					expect(showSuccess).toHaveBeenCalledTimes(0)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(4)
				})
				it('should set sso settings on save', async () => {
					const providerSelect = wrapper.find(selectors.providerSelect)
					await providerSelect.vm.$emit('option:selected', 'new-provider')
					const tokenExchangeSwitch = wrapper.find(selectors.tokenExchangeSwitch)
					tokenExchangeSwitch.vm.$emit('update:checked', false)
					const saveFormButton = wrapper.find(selectors.saveFormButton)
					await saveFormButton.vm.$emit('click')
					await localVue.nextTick()

					expect(saveAdminConfig).toBeCalledTimes(1)
					expect(saveAdminConfig).toBeCalledWith({
						sso_provider_type: SSO_PROVIDER_TYPE.external,
						oidc_provider: 'new-provider',
						targeted_audience_client_id: null,
						token_exchange: false,
					})
					expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
					expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
					expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
					expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
					expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

					expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.savedForm.oidc_provider).toBe('new-provider')
					expect(wrapper.vm.savedForm.token_exchange).toBe(false)
					expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe(null)
					expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.external)
					expect(wrapper.vm.currentForm.oidc_provider).toBe('new-provider')
					expect(wrapper.vm.currentForm.token_exchange).toBe(false)
					expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe(null)
					expect(showSuccess).toHaveBeenCalledTimes(1)
					expect(showError).toHaveBeenCalledTimes(0)
					expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(3)
					toMatchSerializedSnapshot(wrapper.html())
				})

				describe('change to Nextcloud Hub', () => {
					it('should set sso settings on save', async () => {
						const ssoNextcloudRadioBox = wrapper.find(selectors.ssoNextcloudRadioBox)
						ssoNextcloudRadioBox.vm.$emit('update:checked', SSO_PROVIDER_TYPE.nextcloudHub)
						await localVue.nextTick()
						expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
						expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)

						const saveFormButton = wrapper.find(selectors.saveFormButton)
						await saveFormButton.vm.$emit('click')
						await localVue.nextTick()

						expect(saveAdminConfig).toBeCalledTimes(1)
						expect(saveAdminConfig).toBeCalledWith({
							sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
							oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
							targeted_audience_client_id: 'op-client-id',
							token_exchange: null,
						})
						expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
						expect(wrapper.find(selectors.formHeading).attributes().iscomplete).toBe('true')
						expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
						expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
						expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)

						expect(wrapper.vm.savedForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
						expect(wrapper.vm.savedForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
						expect(wrapper.vm.savedForm.token_exchange).toBe(null)
						expect(wrapper.vm.savedForm.targeted_audience_client_id).toBe('op-client-id')
						expect(wrapper.vm.currentForm.sso_provider_type).toBe(SSO_PROVIDER_TYPE.nextcloudHub)
						expect(wrapper.vm.currentForm.oidc_provider).toBe(SSO_PROVIDER_LABEL.nextcloudHub)
						expect(wrapper.vm.currentForm.token_exchange).toBe(null)
						expect(wrapper.vm.currentForm.targeted_audience_client_id).toBe('op-client-id')
						expect(wrapper.vm.loading).toBe(false)
						expect(showSuccess).toHaveBeenCalledTimes(1)
						expect(showError).toHaveBeenCalledTimes(0)
						expect(wrapper.findAll(selectors.fieldValue)).toHaveLength(2)
					})
				})
			})
		})
	})

	describe('save failure', () => {
		beforeEach(() => {
			saveAdminConfig.mockImplementation(() => {
				throw new Error('Save failed')
			})
		})

		it('should show error message on save failure', async () => {
			const wrapper = getWrapper()
			await wrapper.setData({
				currentForm: {
					sso_provider_type: SSO_PROVIDER_TYPE.nextcloudHub,
					oidc_provider: SSO_PROVIDER_LABEL.nextcloudHub,
					token_exchange: null,
					targeted_audience_client_id: 'op-client-id',
				},
			})
			await wrapper.vm.saveSettings()
			await localVue.nextTick()

			expect(saveAdminConfig).toBeCalledTimes(1)
			expect(showError).toHaveBeenCalledTimes(1)
			expect(showSuccess).toHaveBeenCalledTimes(0)

			expect(wrapper.vm.formMode).toBe(F_MODES.NEW)
			expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe(undefined)
			expect(wrapper.vm.loading).toBe(false)
			toMatchSerializedSnapshot(wrapper.html())
		})
	})
})

function getWrapper({ data = {}, props = {} } = {}) {
	return shallowMount(FormSSOSettings, {
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

function toMatchSerializedSnapshot(element) {
	element = element.replace(/ id="[^"]+"/g, ' id="__ID__"').replace(/ uid="[^"]+"/g, ' uid="__UID__"')
	expect(element).toMatchSnapshot()
}
