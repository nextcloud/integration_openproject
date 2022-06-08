/* jshint esversion: 8 */

import axios from '@nextcloud/axios'
import { createLocalVue, shallowMount, mount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'
import { F_MODES } from '../../../src/utils'

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

const selectors = {
	oauthInstance: '#openproject-oauth-instance',
	oauthClientId: '#openproject-client-id',
	oauthClientSecret: '#openproject-client-secret',
	serverHostForm: '.openproject-server-host',
	opOauthForm: '.openproject-oauth-values',
	ncOauthForm: '.nextcloud-oauth-values',
	resetServerHostButton: '[data-test-id="reset-server-host-btn"]',
	textInputWrapper: '.text-input-wrapper',
	submitButton: '.submit-btn',
	cancelEditServerHostForm: '[data-test-id="cancel-edit-server-host-btn"]',
	resetOPOAuthFormButton: '[data-test-id="reset-op-oauth-btn"]',
	submitOPOAuthFormButton: '[data-test-id="submit-op-oauth-btn"]',
	opOauthClientIdInput: '#openproject-oauth-client-id',
	opOauthClientSecretInput: '#openproject-oauth-client-secret',

}

// eslint-disable-next-line no-import-assign
initialState.loadState = jest.fn(() => {
	return {
		oauth_instance_url: null,
		oauth_client_id: null,
		oauth_client_secret: null,
	}
})

describe('AdminSettings', () => {
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
						clientId: 'abcd',
						clientSecret: 'woljklkdsdk',
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
					client_id: 'sldjfslfjlsdjfsd',
					client_secret: 'sljlsjdflskdfslkdjflsdjf',
					nc_oauth_client: {
						clientId: 'aslkdjflskdjflkd',
						clientSecret: 'sljlfkjlskjdflkd',
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
						oauth_instance_url: 'http://hello.com',
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
			})
		})
		describe('edit mode', () => {
			describe('submit button', () => {
				it('should set the input to error state when the url is invalid when clicked', async () => {
					jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({ data: false }))
					const saveOPOptionsSpy = jest.spyOn(AdminSettings.methods, 'saveOPOptions')
						.mockImplementationOnce(() => jest.fn())

					const wrapper = getMountedWrapper()
					await wrapper.setData({
						state: {
							oauth_instance_url: 'https://hero.com',
						},
					})

					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)

					const submitServerFormButton = wrapper.find('.submit-btn')
					await submitServerFormButton.trigger('click')

					for (let i = 0; i <= 100; i++) {
						await wrapper.vm.$nextTick()
					}

					const serverHostForm = wrapper.find(selectors.serverHostForm)
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(false)
					expect(serverHostForm.find(selectors.textInputWrapper)).toMatchSnapshot()
					expect(saveOPOptionsSpy).toBeCalledTimes(0)
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
					await serverHostForm.find('input').setValue('http://hero.com')
					serverHostForm = wrapper.find(selectors.serverHostForm)
					await serverHostForm.find(selectors.submitButton).trigger('click')

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
					const submitButton = serverHostForm.find('.submit-btn')
					expect(submitButton.props().isDisabled).toBe(true)
				})
				it('should unset the disabled state on input', async () => {
					const wrapper = getMountedWrapper({
						state: { oauth_instance_url: '' },
					})
					let submitButton
					const serverHostForm = wrapper.find(selectors.serverHostForm)
					submitButton = serverHostForm.find(selectors.submitButton)
					expect(submitButton.classes()).toContain('b-disabled')
					await serverHostForm.find('input').setValue('a')

					submitButton = serverHostForm.find(selectors.submitButton)
					expect(submitButton.classes()).not.toContain('b-disabled')
				})
			})
			describe('cancel button', () => {
				let wrapper, editButton
				beforeEach(async () => {
					wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://hello.com',
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
						oauth_instance_url: 'http://hero.com',
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

					const expectedDialogMessage = 'Are you sure you want to replace the OpenProject OAuth client details?'
						+ ' Every currently connected user will need to re-authorize this Nextcloud'
						+ ' instance to have access to their OpenProject account.'
					const expectedDialogTitle = 'Replace OpenProject OAuth client details'
					const expectedButtonSet = {
						cancel: 'Cancel',
						confirm: 'Yes, Replace',
						confirmClasses: 'error',
						type: 70,
					}
					expect(confirmSpy).toHaveBeenCalledWith(
						expectedDialogMessage,
						expectedDialogTitle,
						expectedButtonSet,
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
						oauth_instance_url: 'http://hero.com',
						client_id: '',
						client_secret: '',
						nc_oauth_client: null,
					},
				})
			})
			afterEach(() => {
				jest.resetAllMocks()
				wrapper.destroy()
			})
			it('should show the form and hide the field values', () => {
				expect(wrapper.find(selectors.opOauthForm)).toMatchSnapshot()
			})
			describe('submit button', () => {
				it('should be enabled with complete client values', async () => {
					let submitButton
					submitButton = wrapper.find(selectors.submitOPOAuthFormButton)
					expect(submitButton.classes()).toContain('b-disabled')
					await wrapper.find(selectors.opOauthClientIdInput).setValue('qwerty')
					await wrapper.find(selectors.opOauthClientSecretInput).setValue('qwerty')

					submitButton = wrapper.find(selectors.submitOPOAuthFormButton)
					expect(submitButton.classes()).not.toContain('b-disabled')
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
									oauth_instance_url: 'http://hero.com',
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
							await wrapper.find(selectors.submitOPOAuthFormButton).trigger('click')
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
						oauth_instance_url: 'http://hero.com',
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
				it('should trigger the confirm dialog', () => {
					jest.spyOn(AdminSettings.methods, 'translate')
						.mockImplementation((text) => text)
					const wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://hero.com',
							client_id: 'op-client-id',
							client_secret: 'op-client-secret',
							nc_oauth_client: {
								clientId: 'nc-clientid',
								clientSecret: 'nc-clientsecret',
							},
						},
					})

					const resetButton = wrapper.find(selectors.resetOPOAuthFormButton)
					resetButton.trigger('click')
					expect(confirmSpy).toBeCalledTimes(1)
					expect(confirmSpy).toBeCalledWith(
						'Are you sure you want to replace the OpenProject OAuth client details?'
						+ ' Every currently connected user will need to re-authorize this Nextcloud'
						+ ' instance to have access to their OpenProject account.',
						'Replace OpenProject OAuth client details',
						{ cancel: 'Cancel', confirm: 'Yes, Replace', confirmClasses: 'error', type: 70 },
						expect.any(Function),
						true
					)
					wrapper.destroy()
				})
				it('should create new client on confirm', async () => {
					jest.restoreAllMocks()
					jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({
							data: {
								clientId: 'new-client-id77',
								clientSecret: 'new-client-secret77',
							},
						}))
					const wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: 'http://hero.com',
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
					wrapper.destroy()
				})
			})
		})
		describe('edit mode', () => {
			it('should show the form and hide the field values', async () => {
				jest.spyOn(AdminSettings.methods, 'translate')
					.mockImplementation((text) => text)
				const wrapper = getWrapper({
					state: {
						oauth_instance_url: 'http://hero.com',
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
							oauth_instance_url: 'http://hero.com',
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
						.find(selectors.submitButton)
						.trigger('click')
					expect(wrapper.vm.formMode.ncOauth).toBe(F_MODES.VIEW)
				})
			})
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
