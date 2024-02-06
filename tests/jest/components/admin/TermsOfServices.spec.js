/* jshint esversion: 8 */

import { mount, createLocalVue } from '@vue/test-utils'
import TermsOfServiceUnsigned from '../../../../src/components/admin/TermsOfServiceUnsigned.vue'
import axios from '@nextcloud/axios'
import * as dialogs from '@nextcloud/dialogs'
const localVue = createLocalVue()
jest.mock('@nextcloud/auth')
jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

describe('TermsOfServiceUnsigned.vue', () => {
	const signTermsOfServiceButtonSelector = '[data-test-id="sign-tos-for-user-openproject"]'
	const termsOfServiceModalSelector = '.tos-modal-wrapper'
	describe('sign terms of services modal', () => {
		it('should have a button "Sign Terms Of Services"', async () => {
			const wrapper = mountWrapper()
			const signTermsOfServiceButtonWrapper = wrapper.find(signTermsOfServiceButtonSelector)
			expect(signTermsOfServiceButtonWrapper.isVisible()).toBe(true)
			expect(signTermsOfServiceButtonWrapper.text()).toBe('Sign Terms of services')
		})

		describe('on trigger "Sign Terms of services"', () => {
			let wrapper
			let axiospostSpy
			beforeEach(async () => {
				axiospostSpy = jest.spyOn(axios, 'post')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: { result: true },
					}))
				wrapper = mountWrapper()
				const signTermsOfServiceButton = wrapper.find(signTermsOfServiceButtonSelector)
				await signTermsOfServiceButton.trigger('click')
				await localVue.nextTick()
			})
			afterEach(async () => {
				axiospostSpy.mockReset()
				dialogs.showSuccess.mockReset()
				dialogs.showError.mockReset()
			})
			it('should sign all terms of services', async () => {
				expect(axiospostSpy).toHaveBeenCalledTimes(1)
			})

			it('should give success toast on successful', () => {
				expect(dialogs.showSuccess).toBeCalledTimes(1)
			})

			it('should give error toast on failure', async () => {
				jest.spyOn(axios, 'post')
					.mockImplementation(() => Promise.reject(new Error('Throw error')))
				const wrapper = mountWrapper()
				const signTermsOfServiceButton = wrapper.find(signTermsOfServiceButtonSelector)
				await signTermsOfServiceButton.trigger('click')
				await localVue.nextTick()
				expect(dialogs.showError).toBeCalledTimes(1)
			})

			it('should close modal', async () => {
				expect(wrapper.vm.showModal).toBe(false)
				expect(wrapper.find(termsOfServiceModalSelector).exists()).toBeFalsy()
			})
		})
	})
})
function mountWrapper() {
	return mount(TermsOfServiceUnsigned, {
		localVue,
		attachTo: document.body,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		stubs: {
			NcModal: true,
		},
		data: () => ({
			showModal: true,
		}),
	})
}
