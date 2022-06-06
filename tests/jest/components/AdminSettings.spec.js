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
	formHeading: '.form-heading',
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
	describe("when the form is loaded with different states", () => {
		it.each([
			{
				state: {
					oauth_instance_url: null,
					client_id: null,
					client_secret: null,
					nc_oauth_client: null,
				},
				expectedFormMode: {
					server: F_MODES.EDIT,
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.DISABLE,
				},
				expectedFormState: {
					server: F_STATES.INCOMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			},
			{
				state: {
					oauth_instance_url: 'https://openproject.example.com',
					client_id: null,
					client_secret: null,
					nc_oauth_client: null,
				},
				expectedFormMode: {
					server: F_MODES.VIEW,
					opOauth: F_MODES.EDIT,
					ncOauth: F_MODES.DISABLE,
				},
				expectedFormState: {
					server: F_STATES.COMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			},
			{
				state: {
					oauth_instance_url: null,
					client_id: "abcd",
					client_secret: "abcdefgh",
					nc_oauth_client: null,
				},
				expectedFormMode: {
					server: F_MODES.EDIT,
					opOauth: F_MODES.VIEW,
					ncOauth: F_MODES.DISABLE,
				},
				expectedFormState: {
					server: F_STATES.INCOMPLETE,
					opOauth: F_STATES.COMPLETE,
					ncOauth: F_STATES.INCOMPLETE,
				}
			},
			{
				state: {
					oauth_instance_url: null,
					client_id: null,
					client_secret: null,
					nc_oauth_client: {
						clientId: 'abcd',
						clientSecret: 'abcd'
					},
				},
				expectedFormMode: {
					server: F_MODES.EDIT,
					opOauth: F_MODES.DISABLE,
					ncOauth: F_MODES.VIEW,
				},
				expectedFormState: {
					server: F_STATES.INCOMPLETE,
					opOauth: F_STATES.INCOMPLETE,
					ncOauth: F_STATES.COMPLETE,
				}
			}
		])("on form load", ({state, expectedFormMode, expectedFormState}) => {
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
		it.each(['', null])("should set the submit button as disabled when url is empty", (value) => {
			const wrapper = getWrapper({
				state: { oauth_instance_url: value }
			})
			const serverHostForm = wrapper.find(selectors.serverHostForm)
			const submitButton = serverHostForm.find('.submit-btn')
			expect(submitButton.classes()).toContain('submit-disabled')
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
