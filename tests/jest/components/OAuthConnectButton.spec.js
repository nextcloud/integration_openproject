/* jshint esversion: 8 */

import { mount, createLocalVue } from '@vue/test-utils'
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
	describe('when the admin config is not okay', () => {
		it('should show message', async () => {
			wrapper = getWrapper({ isAdminConfigOk: false })
			expect(wrapper).toMatchSnapshot()
		})
	})
	describe('when the admin config is ok', () => {
		beforeEach(() => {
			delete window.location
			window.location = { replace: jest.fn(), pathname: '/index.php/apps/files/' }
			wrapper = getWrapper()
		})
		describe('on successful retrieving of the OP OAuth URI', () => {
			beforeEach(() => {
				axios.get.mockImplementationOnce(() =>
					Promise.resolve({ data: 'http://openproject/oauth' })
				)
				axios.put.mockImplementationOnce(() =>
					Promise.resolve({}),
				)
			})
			it('saves the state to user config', async () => {
				wrapper.find('button').trigger('click')
				await localVue.nextTick()
				expect(axios.put).toHaveBeenCalledWith(
					'http://localhost/apps/integration_openproject/config',
					{
						values: {
							oauth_journey_starting_page: expect.stringMatching(/{.*}/),
						},
					},
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
	return mount(OAuthConnectButton, {
		localVue,
		mocks: {
			t: (app, msg) => msg,
		},
		propsData: {
			isAdminConfigOk: true,
			...props,
		},
	})
}
