/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import OAuthConnectButton from '../../../src/components/OAuthConnectButton.vue'
import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/dialogs')

const { location } = window
const localVue = createLocalVue()

describe('OAuthConnectButton.vue Test', () => {
	let wrapper
	beforeEach(() => {
		delete window.location
		window.location = { replace: jest.fn() }
		wrapper = shallowMount(OAuthConnectButton, {
			localVue,
			mocks: {
				t: (app, msg) => msg,
			},
			propsData: {
				requestUrl: 'http://openproject/oauth/',
			},
		})
	})
	afterEach(() => {
		window.location = location
	})
	describe('on successful saving of the state', () => {
		beforeEach(() => {
			axios.put.mockImplementationOnce(() =>
				Promise.resolve({}),
			)
		})
		it('saves the state to user config', async () => {
			wrapper.find('button').trigger('click')
			await localVue.nextTick()
			expect(axios.put).toHaveBeenCalledWith(
				'http://localhost/apps/integration_openproject/config',
				{ values: { oauth_state: expect.stringMatching(/[a-z0-9]{9}/) } },
			)
		})
		it('redirects to the openproject oauth uri', async () => {
			wrapper.find('button').trigger('click')
			await localVue.nextTick()
			expect(window.location.replace).toHaveBeenCalledWith(
				expect.stringMatching(/http:\/\/openproject\/oauth\/&state=[a-z0-9]{9}/),
			)
		})
	})
	describe('on unsuccessful saving of the state', () => {
		beforeEach(() => {
			const err = new Error()
			err.message = 'some issue'
			axios.put.mockRejectedValueOnce(err)
		})
		it('shows an error', async () => {
			dialogs.showError.mockImplementationOnce()
			wrapper.find('button').trigger('click')
			await localVue.nextTick()
			expect(dialogs.showError).toHaveBeenCalledWith(
				'Failed to save OpenProject OAuth state: some issue'
			)
			expect(window.location.replace).not.toHaveBeenCalled()
		})
	})
})
