/* jshint esversion: 8 */

import axios from '@nextcloud/axios'
import {createLocalVue, shallowMount, mount} from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'
import {F_STATES, F_MODES} from '../../../src/utils'
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

const selectors = {
	oauthInstance: '#openproject-oauth-instance',
	oauthClientId: '#openproject-client-id',
	oauthClientSecret: '#openproject-client-secret',
	serverHostForm: '.openproject-server-host',
	opOauthForm: '.openproject-oauth-values',
	ncOauthForm: '.nextcloud-oauth-values',
	textInputWrapper: '.text-input-wrapper',
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
		jest.clearAllMocks()
		jest.restoreAllMocks()
	})

	describe("form mode and form state", () => {
		it.each([
			[
				"with empty state",
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
					server: F_STATES.INCOMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			],
			[
				"with incomplete OpenProject OAuth values",
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
					server: F_STATES.COMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			],
			[
				"with complete OpenProject OAuth values",
				{
					oauth_instance_url: 'https://openproject.example.com',
					client_id: "abcd",
					client_secret: "abcdefgh",
					nc_oauth_client: null,
				},
				{
					server: F_MODES.VIEW,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.DISABLE,
				},
				{
					server: F_STATES.COMPLETE,
					opOauth: F_STATES.COMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			],
			[
				"with Nextcloud OAuth values",
				{
					oauth_instance_url: null,
					client_id: null,
					client_secret: null,
					nc_oauth_client: {
						clientId: 'abcd',
						clientSecret: 'abcd'
					},
				},
				{
					server: F_MODES.EDIT,
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.VIEW,
				},
				{
					server: F_STATES.INCOMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.COMPLETE,
				}
			]
		])("when the form is loaded %s", (name, state, expectedFormMode, expectedFormState) => {
			const wrapper = getWrapper({state})
			expect(wrapper.vm.formMode.server).toBe(expectedFormMode.server)
			expect(wrapper.vm.formMode.opOauth).toBe(expectedFormMode.opOauth)
			expect(wrapper.vm.formMode.ncOauth).toBe(expectedFormMode.ncOauth)

			expect(wrapper.vm.formState.server).toBe(expectedFormState.server)
			expect(wrapper.vm.formState.opOauth).toBe(expectedFormState.opOauth)
			expect(wrapper.vm.formState.ncOauth).toBe(expectedFormState.ncOauth)
		})
	})
	describe("server host url form", () => {
		describe("view mode and completed state", () => {
			let wrapper, resetButton
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						oauth_instance_url: "http://hello.com"
					}
				})
				resetButton = wrapper.find('[data-test-id="reset-server-host-btn"]')
			})
			it("should show field value and hide the input field", () => {
				expect(wrapper.find(selectors.serverHostForm)).toMatchSnapshot()
			})
			describe("reset button", () => {
				it("should be visible when the form is in completed state", async () => {
					expect(resetButton).toMatchSnapshot()
				})
				it("should set the form to 'edit' mode on click", async () => {
					await resetButton.trigger("click")
	
					expect(wrapper.vm.formMode.server).toBe(F_MODES.EDIT)
				})
				it("should not change the form state", async () => {
					await resetButton.trigger("click")
	
					expect(wrapper.vm.formState.server).toBe(F_STATES.COMPLETE)
				})
			})
		})
		describe("edit mode", () => {
			describe("submit button", () => {
				it("should set the input to error state when the url is invalid when clicked", async () => {
					let serverHostForm;
					const axiosSpyIsValidOPInstance = jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({ data: false }))
					const saveOPOptionsSpy = jest.spyOn(AdminSettings.methods, "saveOPOptions")
						.mockImplementationOnce(() => Promise.resolve({ data: false }))
					const wrapper = getMountedWrapper()
					serverHostForm = wrapper.find(selectors.serverHostForm)
					await wrapper.setData({
						state: {
							oauth_instance_url: "https://hero.com"
						}
					})
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)
					const submitServerFormButton = wrapper.find(".submit-btn")
					await submitServerFormButton.trigger('click')
					
					for (let i = 0; i<=100; i++) {
						await wrapper.vm.$nextTick()
					}
					
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(false)
					serverHostForm = wrapper.find(selectors.serverHostForm)
					expect(serverHostForm.find(".text-input-wrapper")).toMatchSnapshot()
					expect(saveOPOptionsSpy).toBeCalledTimes(0)
				})
				it("should save the form when the url is valid", async () => {
					let serverHostForm
					const axiosSpyIsValidOPInstance = jest.spyOn(axios, 'post')
							.mockImplementationOnce(() => Promise.resolve({ data: true }))
					const saveOPOptionsSpy = jest.spyOn(axios, 'put')
							.mockImplementationOnce(() => Promise.resolve({ data: true }))
					const wrapper = getMountedWrapper({state: {
						oauth_instance_url: '',
						client_id: null,
						client_secret: null,
						nc_oauth_client: null
					}})
					serverHostForm = wrapper.find(selectors.serverHostForm)
					expect(wrapper.vm.formState.server).toBe(F_STATES.INCOMPLETE)
					expect(wrapper.vm.formMode.server).toBe(F_MODES.EDIT)
					await serverHostForm.find('input').setValue('http://hero.com')
					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(null)
					serverHostForm = wrapper.find(selectors.serverHostForm)
					const submitServerFormButton = serverHostForm.find(".submit-btn")
					await submitServerFormButton.trigger('click')

					for (let i = 0; i<=100; i++) {
						await wrapper.vm.$nextTick()
					}

					expect(wrapper.vm.isOpenProjectInstanceValid).toBe(true)
					expect(saveOPOptionsSpy).toBeCalledTimes(1)
					expect(wrapper.vm.formState.server).toBe(F_STATES.COMPLETE)
					expect(wrapper.vm.formMode.server).toBe(F_MODES.VIEW)
				})
			})
			describe("disabled state", () => {
				it.each(['', null])("should set the submit button as disabled when url is empty", (value) => {
					const wrapper = getWrapper({
						state: { oauth_instance_url: value }
					})
					const serverHostForm = wrapper.find(selectors.serverHostForm)
					const submitButton = serverHostForm.find('.submit-btn')
					expect(submitButton.classes()).toContain('submit-disabled')
				})
				it("should unset the disabled state on input", async () => {
					const wrapper = getMountedWrapper({
						state: { oauth_instance_url: "" }
					})
					let serverHostForm, submitButton;
					serverHostForm = wrapper.find(selectors.serverHostForm)
					submitButton = serverHostForm.find('.submit-btn')
					expect(submitButton.classes()).toContain('submit-disabled')
					await serverHostForm.find('input').setValue('a')
	
					submitButton = serverHostForm.find('.submit-btn')
					expect(submitButton.classes()).not.toContain('submit-disabled')
				})
			})
			describe("cancel button", () => {
				let wrapper, editButton
				beforeEach(async () => {
					wrapper = getMountedWrapper({
						state: {
							oauth_instance_url: "http://hello.com"
						}
					})
					await wrapper.setData({
						formMode: {
							server: F_MODES.EDIT
						}
					})
					editButton = wrapper.find('[data-test-id="cancel-edit-server-host-btn"]')
				})
				it("should be visible when the form is in completed state with edit mode", async () => {
					expect(editButton).toMatchSnapshot()
				})
				it("should set the form to view mode on click", async () => {
					await editButton.trigger("click")
					expect(wrapper.vm.formMode.server).toBe(F_MODES.VIEW)
				})
			})
		})
	})

	describe("OpenProjec OAuth values form", () => {
		describe("view mode and completed state", () => {
			let wrapper, opOAuthForm, resetButton
			const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
			const saveOPOptionsSpy = jest.spyOn(axios, 'put')
							.mockImplementationOnce(() => Promise.resolve({ data: true }))
			beforeEach(() => {
				wrapper = getMountedWrapper({
					state: {
						oauth_instance_url: 'http://hero.com',
						client_id: 'abcd',
						client_secret: "abcdefgh",
						nc_oauth_client: null
					}
				})
				opOAuthForm = wrapper.find(selectors.opOauthForm)
				resetButton = opOAuthForm.find('[data-test-id="reset-op-oauth-btn"]')
			})
			it("should show field values and hide the form if server host form is complete", () => {
				expect(opOAuthForm).toMatchSnapshot()
			})
			describe("reset button", () => {
				it("should be visible", () => {
					expect(resetButton).toMatchSnapshot()
				})
				it("should trigger confirm dialog on click", async () => {
					await resetButton.trigger("click")
					expect(confirmSpy).toBeCalledTimes(1)

					const expectedDialogMessage = 'Are you sure you want to replace the OpenProject OAuth client details?'
					+ ' Every currently connected user will need to re-authorize this Nextcloud'
					+ ' instance to have access to their OpenProject account.'
					const expectedDialogTitle = 'Replace OpenProject OAuth client details'
					const expectedButtonSet = {
						cancel: "Cancel",
						confirm: "Yes, Replace",
						confirmClasses: "error",
						type: 70
					}
					expect(confirmSpy).toHaveBeenCalledWith(
						expectedDialogMessage,
						expectedDialogTitle,
						expectedButtonSet,
						expect.any(Function),
						true
					)
				})
				it("should clear values on confirm", async () => {

					await wrapper.vm.clearOPClientValues()

					expect(saveOPOptionsSpy).toBeCalledTimes(1)
					expect(wrapper.vm.state.client_id).toBe(null)
				})
			})
		})
		describe("edit mode", () => {

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
