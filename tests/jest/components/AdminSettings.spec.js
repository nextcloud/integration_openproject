/* jshint esversion: 8 */

import { createLocalVue, shallowMount } from '@vue/test-utils'
import AdminSettings from '../../../src/components/AdminSettings'
import * as initialState from '@nextcloud/initial-state'

const localVue = createLocalVue()

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
		])('should have on readonly attribute after the input is focused', async (inputSelector) => {
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
	})
})

function getWrapper() {
	return shallowMount(AdminSettings, {
		localVue,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
	})
}
