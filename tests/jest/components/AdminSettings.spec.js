/* jshint esversion: 8 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'

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
	describe('on input event', () => {
		beforeEach(() => {
			jest.clearAllMocks()
		})
		it.each([
			selectors.oauthInstance,
			selectors.oauthClientId,
			selectors.oauthClientId,
		])('should call "onInput" method', async (inputSelector) => {
			const onInputSpy = jest.spyOn(AdminSettings.methods, 'onInput')
				.mockImplementationOnce(() => {
				})
			const wrapper = getWrapper()
			const inputField = wrapper.find(inputSelector)

			await inputField.trigger('input')

			expect(onInputSpy).toHaveBeenCalledTimes(1)
		})
		it('should not asks for confirmation if admin config status is not ok beforehand', async () => {
			jest.useFakeTimers()
			const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
			const wrapper = getWrapper({
				isAdminConfigOk: false,
			})
			const inputField = wrapper.find(selectors.oauthClientId)
			await inputField.setValue('test')

			jest.runAllTimers()

			expect(confirmSpy).toBeCalledTimes(0)
		})
		it('should ask for confirmation if admin config status is ok beforehand', async () => {
			jest.useFakeTimers()
			const confirmSpy = jest.spyOn(global.OC.dialogs, 'confirmDestructive')
			const wrapper = getWrapper({
				isAdminConfigOk: true,
			})
			const inputField = wrapper.find(selectors.oauthClientId)
			await inputField.setValue('test')

			jest.runAllTimers()

			expect(confirmSpy).toBeCalledTimes(1)

			const expectedDialogMessage = 'Are you sure you want to update the admin settings? After saving, every connected users must need to re-connect to the Openproject instance.'
			const expectedDialogTitle = 'Confirm Update'
			const expectedButtonSet = {
				confirm: 'Update',
				cancel: 'Cancel',
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
