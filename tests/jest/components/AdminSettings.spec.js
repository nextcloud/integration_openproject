/* jshint esversion: 8 */

import axios from '@nextcloud/axios'
import { createLocalVue, shallowMount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'

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
	saveConfigButton: '.save-config-btn',
	updateConfigButton: '.update-config-btn',
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
	describe('input readonly attribute on focus', () => {
		it.each([
			selectors.oauthClientId,
			selectors.oauthClientSecret,
		])('should not have readonly attribute after the input is focused', async (inputSelector) => {
			const wrapper = getWrapper()
			const inputField = wrapper.find(inputSelector)
			expect(inputField.attributes().readonly).toBeTruthy()

			await inputField.trigger('focus')

			expect(inputField.attributes().readonly).toBeFalsy()

			await inputField.trigger('blur')

			expect(inputField.attributes().readonly).toBeFalsy()
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
				const updateConfigButton = wrapper.find(selectors.updateConfigButton)
				const inputField = wrapper.find(selectors.oauthClientId)
				await inputField.setValue('test')
				await updateConfigButton.trigger('click')

				const expectedDialogMessage = 'Are you sure you want to update the Oauth settings for OpenProject?'
				  + ' After saving, all connected users will have to re-connect to the OpenProject instance.'
				const expectedDialogTitle = 'Confirm Update'
				const expectedButtonSet = {
					confirm: 'Update',
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
	})
})

function getWrapper(data = {}) {
	return shallowMount(AdminSettings, {
		localVue,
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
