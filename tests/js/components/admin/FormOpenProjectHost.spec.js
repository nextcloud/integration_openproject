/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2025 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { shallowMount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, vi } from 'vitest'
import flushPromises from 'flush-promises'
import { defineComponent, h } from 'vue'
import { showError, showSuccess } from '@nextcloud/dialogs'

import { F_MODES } from '../../../../src/utils.js'
import { validateOPInstance, saveAdminConfig } from '../../../../src/api/settings.js'
import FormOpenProjectHost from '../../../../src/components/admin/FormOpenProjectHost.vue'

// module mocks
vi.mock(import('@nextcloud/dialogs'), () => ({
	getLanguage: vi.fn(() => ''),
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('../../../../src/api/settings.js'), () => ({
	saveAdminConfig: vi.fn(() => ''),
	validateOPInstance: vi.fn(() => ({
		data: {
			result: true,
		},
	})),
}))

const selectors = {
	formFieldValue: 'field-value-stub',
	serverHostInput: '[data-test-id="openproject-server-host"]',
	saveFormButton: '[data-test-id="save-server-host"]',
	editFormButton: '[data-test-id="edit-server-host"]',
	cancelFormButton: '[data-test-id="cancel-server-host-edit"]',
}

describe('Component: FormOpenProjectHost', () => {
	describe('initial incomplete form', () => {
		let wrapper
		beforeEach(() => {
			wrapper = getWrapper()
		})

		it('should show the required form fields', async () => {
			expect(wrapper.find(selectors.serverHostInput).exists()).toBe(true)
			expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
			expect(wrapper.find(selectors.formFieldValue).exists()).toBe(false)
			expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			expect(wrapper.html()).toMatchSnapshot()
		})
		it('should disable save button', async () => {
			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
		})
		it('should enable save button when url is provided', async () => {
			const validUrl = 'http://example.openproject.test'
			const serverHostInput = wrapper.findComponent(selectors.serverHostInput)

			serverHostInput.vm.$emit('update:modelValue', validUrl)
			await flushPromises()

			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('false')
			expect(wrapper.vm.formDirty).toBe(true)
			expect(wrapper.vm.savedOpenprojectUrl).toBeNull()
		})
		it('should disable save if url is empty', async () => {
			// set new url
			const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
			serverHostInput.vm.$emit('update:modelValue', 'http://new.invalid.test')
			await flushPromises()
			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('false')
			expect(wrapper.vm.formDirty).toBe(true)

			// set empty url
			serverHostInput.vm.$emit('update:modelValue', '')
			await flushPromises()
			expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
			expect(wrapper.vm.formDirty).toBe(false)
		})

		describe('valid url', () => {
			const validUrl = 'http://example.openproject.test'

			beforeEach(async () => {
				wrapper = getWrapper()
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', validUrl)
				await flushPromises()

				validateOPInstance.mockImplementation(() => ({
					data: {
						result: true,
					},
				}))
				saveAdminConfig.mockImplementation(() => Promise.resolve())
			})

			it('should save the url on submit', async () => {
				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBeNull()

				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(validateOPInstance).toHaveBeenCalledWith(validUrl)
				expect(saveAdminConfig).toHaveBeenCalledWith({ openproject_instance_url: validUrl })
				expect(showSuccess).toHaveBeenCalledTimes(1)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.vm.loading).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.formDirty).toBe(false)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)
				expect(wrapper.emitted().formcomplete).toHaveLength(1)
				expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)

				expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.formFieldValue).exists()).toBe(true)
				expect(wrapper.find(selectors.serverHostInput).exists()).toBe(false)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			})

			it('should show error on save failure', async () => {
				saveAdminConfig.mockImplementation(() => Promise.reject(new Error('Failure')))
				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)

				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(validateOPInstance).toHaveBeenCalledWith(validUrl)
				expect(saveAdminConfig).toHaveBeenCalledWith({ openproject_instance_url: validUrl })
				expect(showError).toHaveBeenCalledTimes(1)
				expect(showSuccess).toHaveBeenCalledTimes(0)
				expect(wrapper.vm.loading).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBeNull()
				expect(wrapper.emitted()).not.toHaveProperty('formcomplete')

				expect(wrapper.find(selectors.serverHostInput).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.formFieldValue).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
			})
		})

		describe('invalid url', () => {
			beforeEach(async () => {
				wrapper = getWrapper()
			})

			it.each([
				{
					url: 'http://test.invalid.url',
					result: 'invalid',
					details: '',
					expectedMessage: 'URL is invalid',
					expectedDetails: 'The URL should have the form "https://openproject.org"',
				},
				{
					url: 'http://not.found.test',
					result: 'not_valid_body',
					details: '<body>the complete body of the return</body>',
					expectedMessage: 'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs',
					expectedDetails: '',
				},
				{
					url: 'http://not.found.test',
					result: 'client_exception',
					details: '404 Not Found',
					expectedMessage: 'There is no valid OpenProject instance listening at that URL, please check the Nextcloud logs',
					expectedDetails: 'Response: "404 Not Found"',
				},
				{
					url: 'http://example.openproject.test',
					result: 'server_exception',
					details: '503 Service Unavailable',
					expectedMessage: 'Server replied with an error message, please check the Nextcloud logs',
					expectedDetails: '503 Service Unavailable',
				},
				{
					url: 'http://local.test',
					result: 'local_remote_servers_not_allowed',
					details: '',
					expectedMessage: 'Accessing OpenProject servers with local addresses is not allowed.',
					expectedDetails: 'To be able to use an OpenProject server with a local address, enable the `allow_local_remote_servers` setting. {htmlLink}.',
				},
				{
					url: 'http://redirect.openproject.test',
					result: 'redirected',
					details: 'https://redirected.url',
					expectedMessage: 'The given URL redirects to \'{location}\'. Please do not use a URL that leads to a redirect.',
					expectedDetails: '',
				},
				{
					url: 'http://example.openproject.test',
					result: 'unexpected_error',
					details: '',
					expectedMessage: 'Could not connect to the given URL, please check the Nextcloud logs',
					expectedDetails: '',
				},
				{
					url: 'http://example.openproject.test',
					result: 'network_error',
					details: '',
					expectedMessage: 'Could not connect to the given URL, please check the Nextcloud logs',
					expectedDetails: '',
				},
				{
					url: 'http://example.openproject.test',
					result: 'request_exception',
					details: '',
					expectedMessage: 'Could not connect to the given URL, please check the Nextcloud logs',
					expectedDetails: '',
				},
			])('$result: should show error if url is invalid', async ({ url, result, details, expectedMessage, expectedDetails }) => {
				validateOPInstance.mockImplementation(() => ({
					data: {
						result,
						details,
					},
				}))
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', url)
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBeNull()

				const saveFormButton = wrapper.findComponent(selectors.saveFormButton)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)

				saveFormButton.vm.$emit('click')
				await flushPromises()

				expect(validateOPInstance).toHaveBeenCalledWith(url)
				expect(saveAdminConfig).not.toHaveBeenCalled()
				expect(wrapper.vm.errorMessage).toBe(expectedMessage)
				expect(wrapper.vm.errorDetails).toBe(expectedDetails)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBeNull()
				expect(wrapper.emitted()).not.toHaveProperty('formcomplete')

				expect(wrapper.find(selectors.serverHostInput).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.find(selectors.formFieldValue).exists()).toBe(false)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)
				expect(wrapper.html()).toMatchSnapshot()
			})
		})
	})

	describe('completed form', () => {
		const validUrl = 'http://example.openproject.test'
		let wrapper

		beforeEach(() => {
			wrapper = getWrapper({}, { openprojectUrl: validUrl })
		})

		it('should show text field value with edit button', () => {
			expect(wrapper.find(selectors.formFieldValue).exists()).toBe(true)
			expect(wrapper.find(selectors.editFormButton).exists()).toBe(true)
			expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
			expect(wrapper.find(selectors.serverHostInput).exists()).toBe(false)
			expect(wrapper.find(selectors.saveFormButton).exists()).toBe(false)
			expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(false)

			expect(wrapper.emitted().formcomplete[0][0]).toBeInstanceOf(Function)
			expect(wrapper.vm.serverUrl).toBe(validUrl)
			expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)
			expect(wrapper.html()).toMatchSnapshot()
		})

		describe('edit mode', () => {
			beforeEach(async () => {
				wrapper = getWrapper({}, { openprojectUrl: validUrl })
				const editButton = wrapper.findComponent(selectors.editFormButton)
				editButton.vm.$emit('click')
				await flushPromises()
			})

			it('should show input form fields', async () => {
				expect(wrapper.find(selectors.formFieldValue).exists()).toBe(false)
				expect(wrapper.find(selectors.editFormButton).exists()).toBe(false)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				expect(wrapper.find(selectors.serverHostInput).exists()).toBe(true)
				expect(wrapper.find(selectors.saveFormButton).exists()).toBe(true)
				expect(wrapper.find(selectors.cancelFormButton).exists()).toBe(true)
				expect(wrapper.html()).toMatchSnapshot()
			})
			it('should have disabled save button and enabled cancel button', async () => {
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.find(selectors.cancelFormButton).attributes().disabled).toBe('false')
			})
			it('should show form in view mode on cancel', async () => {
				const cancelButton = wrapper.findComponent(selectors.cancelFormButton)
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)

				// set new url
				const newUrl = 'http://new.openproject.test'
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', newUrl)
				await flushPromises()
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)

				// save button should be enabled
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('false')
				expect(wrapper.find(selectors.cancelFormButton).attributes().disabled).toBe('false')

				await cancelButton.vm.$emit('click')
				await flushPromises()

				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.serverUrl).toBe(validUrl)
				expect(wrapper.vm.formDirty).toBe(false)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)
			})
			it('should reset error messages on cancel', async () => {
				validateOPInstance.mockImplementation(() => ({
					data: {
						result: 'invalid',
						details: '',
					},
				}))
				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)

				// set new url
				const newUrl = 'http://new.invalid.test'
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', newUrl)
				await flushPromises()
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)

				const saveButton = wrapper.findComponent(selectors.saveFormButton)
				await saveButton.vm.$emit('click')
				await flushPromises()
				expect(wrapper.vm.errorMessage).toBe('URL is invalid')
				expect(wrapper.vm.errorDetails).toBe('The URL should have the form "https://openproject.org"')

				const cancelButton = wrapper.findComponent(selectors.cancelFormButton)
				await cancelButton.vm.$emit('click')
				await flushPromises()

				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.serverUrl).toBe(validUrl)
				expect(wrapper.vm.formDirty).toBe(false)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)
				expect(wrapper.vm.errorMessage).toBe('')
				expect(wrapper.vm.errorDetails).toBe('')
			})
			it('should disable save if url is empty', async () => {
				// set new url
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', 'http://new.invalid.test')
				await flushPromises()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('false')
				expect(wrapper.vm.formDirty).toBe(true)

				// set empty url
				serverHostInput.vm.$emit('update:modelValue', '')
				await flushPromises()
				expect(wrapper.find(selectors.saveFormButton).attributes().disabled).toBe('true')
				expect(wrapper.vm.formDirty).toBe(false)
			})
			it('should show form in view mode on save', async () => {
				validateOPInstance.mockImplementation(() => ({
					data: {
						result: true,
					},
				}))
				saveAdminConfig.mockImplementation(() => Promise.resolve())

				expect(wrapper.vm.formMode).toBe(F_MODES.EDIT)
				// set new url
				const newUrl = 'http://new.openproject.test'
				const serverHostInput = wrapper.findComponent(selectors.serverHostInput)
				serverHostInput.vm.$emit('update:modelValue', newUrl)
				await flushPromises()
				expect(wrapper.vm.formDirty).toBe(true)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(validUrl)

				const saveButton = wrapper.findComponent(selectors.saveFormButton)
				await saveButton.vm.$emit('click')
				await flushPromises()

				expect(validateOPInstance).toHaveBeenCalledWith(newUrl)
				expect(saveAdminConfig).toHaveBeenCalledWith({ openproject_instance_url: newUrl })
				expect(showSuccess).toHaveBeenCalledTimes(1)
				expect(showError).toHaveBeenCalledTimes(0)
				expect(wrapper.vm.formMode).toBe(F_MODES.VIEW)
				expect(wrapper.vm.formDirty).toBe(false)
				expect(wrapper.vm.serverUrl).toBe(newUrl)
				expect(wrapper.vm.savedOpenprojectUrl).toBe(newUrl)
				expect(wrapper.emitted().formcomplete).toHaveLength(2)
			})
		})
	})
})

// Mock TextInput component to avoid method not found errors
const TextInput = defineComponent({
  name: 'TextInput',
	methods: {
		focusInputField: vi.fn(),
	},
	render() {
		return h('text-input-stub')
	},
})

function getWrapper(data = {}, props = { }) {
	return shallowMount(FormOpenProjectHost, {
		global: {
			mocks: {
				t: (app, msg) => msg,
			},
			stubs: {
				TextInput,
			},
		},
		props,
		data() {
			return data
		},
	})
}
