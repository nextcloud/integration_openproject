/* jshint esversion: 8 */

import axios from '@nextcloud/axios'
import { createLocalVue, mount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'
import { STATE } from '../../../src/utils'
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
	describe("form heading", () => {
		it("should be green when the form is complete", () => {})
		it("should not be green when the form is incomplete", () => {})
	})
	describe("openproject server form", () => {
		describe('when the form is complete', () => {
			it('should show the green form heading', () => {})
		})
		describe('when the form is incomplete', () => {
			it('should not have the green form heading', () => {})
		})
		describe("when the form is in view mode", () => {
			it("should not show the form", () => {})
			it('should show the form field values', () => {})
		})
		describe("when the form is in edit mode", () => {
			it("should show the form", () => {})
			it('should not show the form field values', () => {})
		})
	})
	describe("openproject oauth values form", () => {
		describe('when the form is complete', () => {
			it('should show the green form heading', () => {})
		})
		describe('when the form is incomplete', () => {
			it('should not have the green form heading', () => {})
		})
		describe("when the form is in view mode", () => {
			it("should not show the form", () => {})
			it('should show the form field values', () => {})
		})
		describe("when the form is in edit mode", () => {
			it("should show the form", () => {})
			it('should not show the form field values', () => {})
		})
	})
	describe("nextcloud oauth values form", () => {
		describe('when the form is complete', () => {
			it('should show the green form heading', () => {})
		})
		describe('when the form is incomplete', () => {
			it('should not have the green form heading', () => {})
		})
		describe("when the form is in view mode", () => {
			it("should not show the form", () => {})
			it('should show the form field values', () => {})
		})
		describe("when the form is in edit mode", () => {
			it("should show the form", () => {})
			it('should not show the form field values', () => {})
		})
	})
	describe('form submit', () => {
		beforeEach(() => {
			jest.clearAllMocks()
		})
		describe('when the admin config is not complete', () => {
			let wrapper, confirmSpy
			beforeEach(() => {
				confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
				wrapper = getWrapper({
					isAdminConfigOk: false,
				})
			})
			it('should show the save button', async () => {
				expect(wrapper.find(selectors.saveConfigButton)).toMatchSnapshot()
				expect(wrapper.find(selectors.updateConfigButton).exists()).toBeFalsy()
			})
			it('should not trigger confirm dialog on save', async () => {
				axios.post.mockImplementationOnce(() =>
					Promise.resolve({ data: true }),
				)
				const saveConfigButton = wrapper.find(selectors.saveConfigButton)
				const inputField = wrapper.find(selectors.oauthClientId)
				await inputField.setValue('test')
				await saveConfigButton.trigger('click')
				expect(confirmSpy).toBeCalledTimes(0)
			})
		})
		describe('when the admin config status is complete', () => {
			let wrapper, confirmSpy
			beforeEach(() => {
				confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
				wrapper = getWrapper({
					isAdminConfigOk: true,
				})
			})
			it('should show the update button', async () => {
				expect(wrapper.find(selectors.updateConfigButton)).toMatchSnapshot()
				expect(wrapper.find(selectors.saveConfigButton).exists()).toBeFalsy()
			})
			it('should trigger confirm dialog on update', async () => {
				axios.post.mockImplementationOnce(() =>
					Promise.resolve({ data: true }),
				)
				const updateConfigButton = wrapper.find(selectors.updateConfigButton)
				const inputField = wrapper.find(selectors.oauthClientId)
				await inputField.setValue('test')
				await updateConfigButton.trigger('click')

				const expectedDialogMessage = 'Are you sure you want to replace the OpenProject OAuth client details?'
				+ ' Every currently connected user will need to re-authorize this Nextcloud instance to have access to their OpenProject account.'
				const expectedDialogTitle = 'Replace OpenProject OAuth client details'
				const expectedButtonSet = {
					confirm: 'Replace',
					cancel: 'Cancel',
					confirmClasses: 'error',
					type: 70,
				}
				expect(confirmSpy).toBeCalledTimes(1)
				expect(confirmSpy).toHaveBeenCalledWith(
					expectedDialogMessage,
					expectedDialogTitle,
					expectedButtonSet,
					expect.any(Function),
					true
				)
			})
		})
		describe('on saveOptions', () => {
			it.each([
				{ initaialComponentStatus: true, responseStatus: false, finalComponentStatus: false },
				{ initaialComponentStatus: false, responseStatus: true, finalComponentStatus: true },
			])('should update the admin config status as provided by the update response', async ({
				initaialComponentStatus,
				responseStatus,
				finalComponentStatus,
			}) => {
				const axiosSpy = jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({
						data: {
							status: responseStatus,
						},
					}))
				const wrapper = getWrapper({
					isAdminConfigOk: initaialComponentStatus,
					state: {
						client_id: 'some-id',
						client_secret: 'some-secret',
						oauth_instance_url: 'some-url',
					},
				})
				await wrapper.vm.saveOptions()
				expect(axiosSpy).toHaveBeenCalledTimes(1)
				expect(wrapper.vm.isAdminConfigOk).toBe(finalComponentStatus)
			})
		})
		describe('check whether OpenProject instance address is valid', () => {
			let axiosSpySaveAdminConfig
			beforeEach(() => {
				axiosSpySaveAdminConfig = jest.spyOn(axios, 'put')
					.mockImplementation(() => Promise.resolve({
						data: {},
					}))
			})
			it('should show the loading indicator while loading for the update button', () => {
				const wrapper = getWrapper({
					loadingState: STATE.LOADING,
					isAdminConfigOk: true,
				})
				expect(wrapper.find(selectors.updateConfigButton)).toMatchSnapshot()
			})
			it('should show the loading indicator while loading for the save button', () => {
				const wrapper = getWrapper({
					loadingState: STATE.LOADING,
					isAdminConfigOk: false,
				})
				expect(wrapper.find(selectors.saveConfigButton)).toMatchSnapshot()
			})

			describe.each([
				['incomplete config', selectors.saveConfigButton, false],
				['complete config', selectors.updateConfigButton, true],
			])('invalid OpenProject instance with %s',
				(name, buttonSelector, isAdminConfigOk) => {
					let axiosSpyIsValidOPInstance
					let saveConfigButton
					let inputField
					beforeEach(async () => {
						axiosSpyIsValidOPInstance = jest.spyOn(axios, 'post')
							.mockImplementationOnce(() => Promise.resolve({ data: false }))

						const wrapper = getWrapper({
							isAdminConfigOk,
						})
						saveConfigButton = wrapper.find(buttonSelector)
						inputField = wrapper.find(selectors.oauthInstance)
						await inputField.setValue('http://no-openproject-here.org')

					})
					it('should not save the config', async function() {
						await saveConfigButton.trigger('click')
						expect(axiosSpyIsValidOPInstance).toHaveBeenCalledTimes(1)
						expect(axiosSpySaveAdminConfig).toHaveBeenCalledTimes(0)
					})
					it('should show an error message', async () => {
						const showErrorSpy = jest.spyOn(dialogs, 'showError')
						await saveConfigButton.trigger('click')
						await localVue.nextTick()
						expect(showErrorSpy).toBeCalledTimes(1)
						showErrorSpy.mockRestore()
					})
					it('should focus the input field', async () => {
						await saveConfigButton.trigger('click')
						await localVue.nextTick()
						try {
							expect(inputField.element).toBe(document.activeElement)
						} catch (e) {
							throw new Error('input field not in focus')
						}
					})
					it('should set the error class for the input field', async () => {
						await saveConfigButton.trigger('click')
						await localVue.nextTick()
						expect(inputField.attributes().class).toContain('error')
					})
				})
			describe('valid OpenProject instance', () => {
				let axiosSpyIsValidOPInstance
				beforeEach(() => {
					axiosSpyIsValidOPInstance = jest.spyOn(axios, 'post')
						.mockImplementationOnce(() => Promise.resolve({ data: true }))
				})
				it('should save the config in case it was incomplete before', async function() {
					const wrapper = getWrapper({
						isAdminConfigOk: false,
					})
					const saveConfigButton = wrapper.find(selectors.saveConfigButton)
					const inputField = wrapper.find(selectors.oauthInstance)
					await inputField.setValue('http://openproject.org')
					await saveConfigButton.trigger('click')
					expect(axiosSpyIsValidOPInstance).toHaveBeenCalledTimes(1)
					expect(axiosSpySaveAdminConfig).toHaveBeenCalledTimes(1)
				})
				it('should show the confirmation dialog in case the config was complete before', async () => {
					const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
					const wrapper = getWrapper({
						isAdminConfigOk: true,
					})
					const updateConfigButton = wrapper.find(selectors.updateConfigButton)
					const inputField = wrapper.find(selectors.oauthInstance)
					await inputField.setValue('http://openproject.org')
					await updateConfigButton.trigger('click')
					expect(axiosSpyIsValidOPInstance).toHaveBeenCalledTimes(1)
					expect(confirmSpy).toBeCalledTimes(1)
				})
			})
		})
	})
})

function getWrapper(data = {}) {
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
