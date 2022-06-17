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
	afterEach(() => {
		window.location = location
		jest.clearAllMocks()
	})
	describe('when the request url is not valid', () => {
		it('should show message', async () => {
			wrapper = getWrapper({ requestUrl: false })
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe('when the request url is valid', () => {
		beforeEach(() => {
			delete window.location
			window.location = { replace: jest.fn() }
			wrapper = getWrapper()
		})
		describe('on successful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				axios.get.mockImplementationOnce(() =>
					Promise.resolve({ data: 'http://openproject/oauth' }),
				)
			})
			it('redirects to the openproject oauth uri', async () => {
				wrapper.find('button').trigger('click')
				await localVue.nextTick()
				expect(window.location.replace).toHaveBeenCalledWith(
					'http://openproject/oauth',
				)
			})
		})
		describe('on unsuccessful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				const err = new Error()
				err.message = 'some issue'
				axios.get.mockRejectedValueOnce(err)
			})
			it('shows an error', async () => {
				dialogs.showError.mockImplementationOnce()
				wrapper.find('button').trigger('click')
				await localVue.nextTick()
				expect(dialogs.showError).toHaveBeenCalledWith(
					'Failed to redirect to OpenProject: some issue'
				)
				expect(window.location.replace).not.toHaveBeenCalled()
			})
		})
	})
})

function getWrapper(props = {}) {
	return shallowMount(OAuthConnectButton, {
		localVue,
		mocks: {
			t: (app, msg) => msg,
		},
		propsData: {
			requestUrl: 'http://openproject/oauth/',
			...props,
		},
	})
}
