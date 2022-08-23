/* jshint esversion: 8 */

import axios from '@nextcloud/axios'
import { createLocalVue, shallowMount, mount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'
import { F_MODES } from '../../../src/utils'
import * as dialogs from '@nextcloud/dialogs'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

const localVue = createLocalVue()

global.OC = {
	dialogs: {
		confirmDestructive: jest.fn(),
		YES_NO_BUTTONS: 70,
	},
}

global.t = (app, text) => text

global.navigator = {
	clipboard: {
		writeText: jest.fn(),
	},
}

const selectors = {
	oauthInstanceInput: '#openproject-oauth-instance',
	oauthClientId: '#openproject-client-id',
	oauthClientSecret: '#openproject-client-secret',
	serverHostForm: '.openproject-server-host',
	opOauthForm: '.openproject-oauth-values',
	ncOauthForm: '.nextcloud-oauth-values',
	resetServerHostButton: '[data-test-id="reset-server-host-btn"]',
	textInputWrapper: '.text-input',
	cancelEditServerHostForm: '[data-test-id="cancel-edit-server-host-btn"]',
	resetOPOAuthFormButton: '[data-test-id="reset-op-oauth-btn"]',
	resetNcOAuthFormButton: '[data-test-id="reset-nc-oauth-btn"]',
	submitOPOAuthFormButton: '[data-test-id="submit-op-oauth-btn"]',
	opOauthClientIdInput: '#openproject-oauth-client-id',
	opOauthClientSecretInput: '#openproject-oauth-client-secret',
	submitServerHostFormButton: '[data-test-id="submit-server-host-form-btn"]',
	submitNcOAuthFormButton: '[data-test-id="submit-nc-oauth-values-form-btn"]',
	resetAllAppSettingsButton: '#reset-all-app-settings-btn',
	defaultUserConfigurationsForm: '.default-prefs',
	defaultEnableNavigation: '#default-prefs--link',
}

const completeIntegrationState = {
	oauth_instance_url: 'http://openproject.com',
	client_id: 'some-client-id-for-op',
	client_secret: 'some-client-secret-for-op',
	nc_oauth_client: {
		clientId: 'something',
		clientSecret: 'something-else',
	},
}

// eslint-disable-next-line no-import-assign
initialState.loadState = jest.fn(() => {
	return {
		oauth_instance_url: null,
		oauth_client_id: null,
		oauth_client_secret: null,
	}
})

describe('AdminSettings.vue', () => {
	afterEach(() => {
		jest.restoreAllMocks()
	})
	const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')

	describe('form mode and completed status', () => {
		it.each([
			[
				'with empty state',
				{
					oauth_instance_url: null,
					client_id: null,
					client_secret: null,
					nc_oauth_client: null,
				},
				{
					server: F_MODES.EDIT,
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.DISABLE,
				},
				{
					server: false,
					opOauth: false,
					ncOauth: false,
				},
			],
			[
				'with incomplete OpenProject OAuth values',
				{
					oauth_instance_url: 'https://openproject.example.com',
					client_id: null,
					client_secret: null,
					nc_oauth_client: null,
				},
				{
					server: F_MODES.VIEW,
					opOauth: F_MODES.EDIT,
					ncOauth: F_MODES.DISABLE,
				},
				{
					server: true,
					opOauth: false,
					ncOauth: false,
				},
			],
			[
				'with complete OpenProject OAuth values',
				{
					oauth_instance_url: 'https://openproject.example.com',
					client_id: 'abcd',
					client_secret: 'abcdefgh',
					nc_oauth_client: null,
				},
				{
					server: F_MODES.VIEW,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.DISABLE,
				},
				{
					server: true,
					opOauth: true,
					ncOauth: false,
				},
			],
			[
				'with everything but empty OpenProject OAuth values',
				{
					oauth_instance_url: 'https://openproject.example.com',
					client_id: null,
					client_secret: null,
					nc_oauth_client: {
						clientId: 'some-client-id-here',
						clientSecret: 'some-client-secret-here',
					},
				},
				{
					server: F_MODES.VIEW,
					opOauth: F_MODES.EDIT,
					ncOauth: F_MODES.VIEW,
				},
				{
					server: true,
					opOauth: false,
					ncOauth: true,
				},
			],
			[
				'with a complete admin settings',
				{
					oauth_instance_url: 'https://openproject.example.com',
					client_id: 'client-id-here',
					client_secret: 'client-id-here',
					nc_oauth_client: {
						clientId: 'nc-client-id-here',
						clientSecret: 'nc-client-secret-here',
					},
				},
				{
					server: F_MODES.VIEW,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.VIEW,
				},
				{
					server: true,
					opOauth: true,
					ncOauth: true,
				},
			],
		])('when the form is loaded %s', (name, state, expectedFormMode, expectedFormState) => {
			const wrapper = getWrapper({ state })
			expect(wrapper.vm.formMode.server).toBe(expectedFormMode.server)
			expect(wrapper.vm.formMode.opOauth).toBe(expectedFormMode.opOauth)
			expect(wrapper.vm.formMode.ncOauth).toBe(expectedFormMode.ncOauth)

			expect(wrapper.vm.isFormCompleted.server).toBe(expectedFormState.server)
			expect(wrapper.vm.isFormCompleted.opOauth).toBe(expectedFormState.opOauth)
			expect(wrapper.vm.isFormCompleted.ncOauth).toBe(expectedFormState.ncOauth)
		})
	})

	describe('server host url form', () => {
		describe('view mode and completed state', () => {
			let wrapper, resetButton
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						oauth_instance_url: 'http://openproject.com',
					},
				})
				resetButton = wrapper.find(selectors.resetServerHostButton)
			})
			it('should show field value and hide the input field', () => {
				expect(wrapper.find(selectors.serverHostForm)).toMatchSnapshot()
			})
			describe('reset button', () => {
				it('should be visible when the form is in completed state', async () => {
					expect(resetButton).toMatchSnapshot()
				})
				it("should set the form to 'edit' mode on click", async () => {
					await resetButton.trigger('click')

					expect(wrapper.vm.formMode.server).toBe(F_MODES.EDIT)
				})
				it('should set the saved url to the edit parameter on click', async () => {
					const wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://openproject.com',
						},
					})
					resetButton = wrapper.find(selectors.resetServerHostButton)

					expect(wrapper.vm.serverHostUrlForEdit).toBe(null)

					await resetButton.trigger('click')

					expect(wrapper.vm.serverHostUrlForEdit).toBe('http://openproject.com')
					expect(wrapper.vm.state.oauth_instance_url).toBe('http://openproject.com')
				})
			})
		})
		describe('edit mode', () => {
			it('should reset open project server host validity check on input', async () => {
				const wrapper = getMountedWrapper()
				await wrapper.setData({
					isOpenProjectInstanceValid: true,
				})

				const oauthInstanceInput = wrapper.find(selectors.oauthInstanceInput)
				await oauthInstanceInput.trigger('input')
				await wrapper.vm.$nextTick()

				expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)
			})

			describe('readonly state', () => {
				let wrapper, oauthInstanceInput
				beforeEach(() => {
					wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: '',
						},
					})
					oauthInstanceInput = wrapper.find(selectors.oauthInstanceInput)
				})
				it('should set the input field to readonly at first', () => {
					expect(oauthInstanceInput).toMatchSnapshot()
				})
				it('should clear the readonly state when clicked on the input', async () => {
					await oauthInstanceInput.trigger('click')
					oauthInstanceInput = wrapper.find(selectors.oauthInstanceInput)
					expect(oauthInstanceInput).toMatchSnapshot()
				})
			})
			describe('submit button', () => {
				it.each([{
					data: false,
					message: 'No OpenProject detected at the URL',
				}, {
					data: 'invalid',
					message: 'OpenProject URL is invalid, provide an URL in the form "https://openproject.org"',
				}, {
					data: 'local_remote_servers_not_allowed',
					message: 'Accessing OpenProject servers with local addresses is not allowed.',
				}])('should set the input to error state when the url is invalid when clicked', async (testCase) => {
					dialogs.showError.mockImplementationOnce()
					jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({ data: testCase.data }))
					const saveOPOptionsSpy = jest.spyOn(AdminSettings.methods, 'saveOPOptions')
						.mockImplementationOnce(() => jest.fn())

					const wrapper = getMountedWrapper()
					await wrapper.setData({
						serverHostUrlForEdit: 'does-not-matter-for-the-test',
					})

					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)

					const submitServerFormButton = wrapper.find(selectors.submitServerHostFormButton)
					await submitServerFormButton.trigger('click')

					for (let i = 0; i <= 100; i++) {
						await wrapper.vm.$nextTick()
					}

					const serverHostForm = wrapper.find(selectors.serverHostForm)
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(false)
					expect(serverHostForm.find(selectors.textInputWrapper)).toMatchSnapshot()
					expect(saveOPOptionsSpy).toBeCalledTimes(0)
					expect(dialogs.showError).toHaveBeenCalledWith(testCase.message)
					jest.clearAllMocks()
				})
				it('should save the form when the url is valid', async () => {
					let serverHostForm
					jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({ data: true }))
					const setAdminConfigAPISpy = jest.spyOn(axios, 'put')
						.mockImplementationOnce(() => Promise.resolve({ data: { status: true } }))

					const wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: '',
							client_id: null,
							client_secret: null,
							nc_oauth_client: null,
						},
					})

					expect(wrapper.vm.formMode.server).toBe(F_MODES.EDIT)
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)
					expect(wrapper.vm.formMode.opOauth).toBe(F_MODES.DISABLE)

					serverHostForm = wrapper.find(selectors.serverHostForm)
					await serverHostForm.find('input').setValue('http://openproject.com')
					serverHostForm = wrapper.find(selectors.serverHostForm)
					await serverHostForm.find(selectors.submitServerHostFormButton).trigger('click')

					for (let i = 0; i <= 100; i++) {
						await wrapper.vm.$nextTick()
					}

					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(true)
					expect(wrapper.vm.formMode.server).toBe(F_MODES.VIEW)
					expect(wrapper.vm.isFormCompleted.server).toBe(true)
					expect(setAdminConfigAPISpy).toBeCalledTimes(1)
					// should set the OpenProject OAuth Values form to edit mode
					expect(wrapper.vm.formMode.opOauth).toBe(F_MODES.EDIT)
				})
			})
			describe('disabled state', () => {
				it.each(['', null])('should set the submit button as disabled when url is empty', (value) => {
					const wrapper = getWrapper({
						state: { oauth_instance_url: value },
					})
					const serverHostForm = wrapper.find(selectors.serverHostForm)
					const submitButton = serverHostForm.find(selectors.submitServerHostFormButton)
					expect(submitButton.attributes().disabled).toBe('true')
				})
				it('should unset the disabled state on input', async () => {
					const wrapper = getMountedWrapper({
						state: { oauth_instance_url: '' },
					})
					let submitButton
					const serverHostForm = wrapper.find(selectors.serverHostForm)
					submitButton = serverHostForm.find(selectors.submitServerHostFormButton)
					expect(submitButton.attributes().disabled).toBe('disabled')

					// first click to enable the input field
					await serverHostForm.find('input').trigger('click')
					await serverHostForm.find('input').setValue('a')

					submitButton = serverHostForm.find(selectors.submitServerHostFormButton)
					expect(submitButton.attributes().disabled).toBe(undefined)
				})
			})
			describe('cancel button', () => {
				let wrapper, editButton
				beforeEach(async () => {
					wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://openproject.com',
						},
					})
					await wrapper.setData({
						formMode: {
							server: F_MODES.EDIT,
						},
					})
					editButton = wrapper.find(selectors.cancelEditServerHostForm)
				})
				it('should be visible when the form is in completed state with edit mode', async () => {
					expect(editButton).toMatchSnapshot()
				})
				it('should set the form to view mode on click', async () => {
					await editButton.trigger('click')
					expect(wrapper.vm.formMode.server).toBe(F_MODES.VIEW)
				})
			})
		})
	})

	describe('OpenProject OAuth values form', () => {
		describe('view mode and completed state', () => {
			let wrapper, opOAuthForm, resetButton
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(() => Promise.resolve({ data: true }))
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						oauth_instance_url: 'http://openproject.com',
						client_id: 'openproject-client-id',
						client_secret: 'openproject-client-secret',
						nc_oauth_client: null,
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
						true
					)
					jest.clearAllMocks()
					wrapper.destroy()
				})
				it('should clear values on confirm', async () => {
					jest.clearAllMocks()
					await wrapper.vm.clearOPOAuthClientValues()

					expect(saveOPOptionsSpy).toBeCalledTimes(1)
					expect(wrapper.vm.state.client_id).toBe(null)
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
				wrapper = getMountedWrapper({
					state: {
						oauth_instance_url: 'http://openproject.com',
						client_id: '',
						client_secret: '',
						nc_oauth_client: null,
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
					expect(submitButton.attributes().disabled).toBe('disabled')
					await wrapper.find(selectors.opOauthClientIdInput).setValue('qwerty')
					await wrapper.find(selectors.opOauthClientSecretInput).setValue('qwerty')

					submitButton = wrapper.find(selectors.submitOPOAuthFormButton)
					expect(submitButton.attributes().disabled).toBe(undefined)
				})
				describe('when clicked', () => {
					describe('when the admin config is ok on save options', () => {
						beforeEach(async () => {
							await wrapper.find(selectors.opOauthClientIdInput).setValue('qwerty')
							await wrapper.find(selectors.opOauthClientSecretInput).setValue('qwerty')
							await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')
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
							const wrapper = getMountedWrapper({
								state: {
									oauth_instance_url: 'http://openproject.com',
									client_id: '',
									client_secret: '',
									nc_oauth_client: {
										clientId: 'abcdefg',
										clientSecret: 'slkjdlkjlkd',
									},
								},
							})
							await wrapper.find(selectors.opOauthClientIdInput).setValue('qwerty')
							await wrapper.find(selectors.opOauthClientSecretInput).setValue('qwerty')
							// await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')
							expect(createNCOAuthClientSpy).not.toHaveBeenCalled()
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
						oauth_instance_url: 'http://openproject.com',
						client_id: 'some-client-id-here',
						client_secret: 'some-client-secret-here',
						nc_oauth_client: {
							clientId: 'some-nc-client-id-here',
							clientSecret: 'some-nc-client-secret-here',
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
					const wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://openproject.com',
							client_id: 'op-client-id',
							client_secret: 'op-client-secret',
							nc_oauth_client: {
								clientId: 'nc-clientid',
								clientSecret: 'nc-clientsecret',
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
					await resetButton.trigger('click')

					expect(confirmSpy).toBeCalledTimes(1)
					expect(confirmSpy).toBeCalledWith(
						expectedConfirmText,
						expectedConfirmTitle,
						expectedConfirmOpts,
						expect.any(Function),
						true
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
							oauth_instance_url: 'http://openproject.com',
							client_id: 'op-client-id',
							client_secret: 'op-client-secret',
							nc_oauth_client: {
								clientId: 'nc-client-id',
								clientSecret: 'nc-client-secret',
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
		describe('edit mode', () => {
			it('should show the form and hide the field values', async () => {
				const wrapper = getWrapper({
					state: {
						oauth_instance_url: 'http://openproject.com',
						client_id: 'op-client-id',
						client_secret: 'op-client-secret',
						nc_oauth_client: {
							clientId: 'nc-client-id',
							clientSecret: 'nc-client-secret',
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
							oauth_instance_url: 'http://openproject.com',
							client_id: 'some-client-id-for-op',
							client_secret: 'some-client-secret-for-op',
							nc_oauth_client: {
								clientId: 'something',
								clientSecret: 'something-else',
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

	describe('reset all app settings button', () => {
		let wrapper
		let confirmSpy

		const { location } = window
		delete window.location
		window.location = { reload: jest.fn() }

		beforeEach(() => {
			wrapper = getMountedWrapper({
				state: {
					oauth_instance_url: 'http://openproject.com',
					client_id: 'some-client-id-for-op',
					client_secret: 'some-client-secret-for-op',
					nc_oauth_client: {
						clientId: 'something',
						clientSecret: 'something-else',
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
			const expectedConfirmTitle = 'Reset OpenProject integration'

			expect(confirmSpy).toBeCalledTimes(1)
			expect(confirmSpy).toBeCalledWith(
				expectedConfirmText,
				expectedConfirmTitle,
				expectedConfirmOpts,
				expect.any(Function),
				true
			)
		})
		it('should reset all settings on confirm', async () => {
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
				.mockImplementationOnce(() => Promise.resolve({ data: true }))
			await wrapper.vm.resetAllAppValues()

			expect(saveOPOptionsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/admin-config',
				{
					values: {
						client_id: null,
						client_secret: null,
						oauth_instance_url: null,
						default_enable_navigation: false,
						default_enable_notifications: false,
						default_enable_unified_search: false,
					},
				}
			)
			axios.put.mockReset()
		})
		it('should reload the window at the end', async () => {
			await wrapper.vm.resetAllAppValues()
			await wrapper.vm.$nextTick()
			expect(window.location.reload).toBeCalledTimes(1)
			window.location = location
		})
	})

	describe('default user configurations form', () => {
		it('should be visible when the integration is complete', () => {
			const wrapper = getMountedWrapper({
				state: completeIntegrationState,
			})
			expect(wrapper.find(selectors.defaultUserConfigurationsForm)).toMatchSnapshot()
		})
		it('should not be visible if the integration is not complete', () => {
			const wrapper = getMountedWrapper({
				state: {
					oauth_instance_url: 'http://openproject.com',
					client_id: 'some-client-id-for-op',
					client_secret: 'some-client-secret-for-op',
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
				state: completeIntegrationState,
			})

			let $defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			await $defaultEnableNavigation.trigger('click')

			$defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			expect(saveDefaultsSpy).toBeCalledTimes(1)
			expect(saveDefaultsSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/admin-config',
				{
					values: {
						default_enable_navigation: true,
						default_enable_notifications: false,
						default_enable_unified_search: false,
					},
				}
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
				state: completeIntegrationState,
			})
			const $defaultEnableNavigation = wrapper.find(selectors.defaultEnableNavigation)
			await $defaultEnableNavigation.trigger('click')
			await localVue.nextTick()

			expect(dialogs.showError).toBeCalledTimes(1)
			expect(dialogs.showError).toBeCalledWith('Failed to save default user configuration: Some message')

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
			}
		},
	})
}
