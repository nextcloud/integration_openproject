/* jshint esversion: 8 */

import { shallowMount, createLocalVue, mount } from '@vue/test-utils'
import PersonalSettings from '../../../src/components/PersonalSettings.vue'
import * as initialState from '@nextcloud/initial-state'
import * as dialogs from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

const localVue = createLocalVue()

// eslint-disable-next-line no-import-assign,import/namespace
initialState.loadState = jest.fn(() => {
	return {
		admin_config_ok: true,
	}
})

jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

describe('PersonalSettings.vue', () => {
	describe('oAuth', () => {
		const oAuthButtonSelector = 'oauthconnectbutton-stub'
		const oAuthDisconnectButtonSelector = '.openproject-prefs--disconnect'
		const connectedAsLabelSelector = '.openproject-prefs--connected label'
		const personalSettingsFormSelector = '.openproject-prefs--form'
		const personalEnableNavigationSelector = '#openproject-prefs--link'
		const personalEnableSearchSelector = '#openproject-prefs--u-search'
		let wrapper

		beforeEach(() => {
			wrapper = shallowMount(PersonalSettings, {
				localVue,
				mocks: {
					t: (app, msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
		})

		describe('when the admin config is okay', () => {
			describe.each([
				{ user_name: 'test', token: '' },
				{ user_name: 'test', token: null },
				{ user_name: 'test', token: undefined },
				{ user_name: '', token: '123' },
				{ user_name: null, token: '123' },
				{ user_name: undefined, token: '123' },
				{ user_name: '', token: '' },
				{ user_name: null, token: '' },
				{ user_name: '', token: null },
			])('when username or token not given', (cases) => {
				beforeEach(async () => {
					await wrapper.setData({
						state: {
							admin_config_ok: true,
							...cases,
						},
					})
				})
				it('oAuth connect button is displayed', () => {
					expect(wrapper.find(oAuthButtonSelector).exists()).toBeTruthy()
				})
				it('personal settings form is not displayed', () => {
					expect(wrapper.find(personalSettingsFormSelector).exists()).toBeFalsy()
				})
				it('oAuth disconnect button is not displayed', () => {
					expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
				})
			})
			describe('when username and token are given', () => {
				beforeEach(async () => {
					await wrapper.setData({
						state: { user_name: 'test', token: '123', admin_config_ok: true },
					})
				})
				it('oAuth connect button is not displayed', () => {
					expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
				})
				it('oAuth disconnect button is displayed', () => {
					expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeTruthy()
				})
				it('connected as label is displayed', () => {
					expect(wrapper.find(connectedAsLabelSelector).text()).toBe('Connected as {user}')
				})
				it('personal settings form is displayed', () => {
					expect(wrapper.find(personalSettingsFormSelector).exists()).toBeTruthy()
				})
			})
		})
		describe('when the admin config is not okay', () => {
			beforeEach(async () => {
				await wrapper.setData({
					state: { user_name: 'test', token: '123', admin_config_ok: false },
				})
			})
			it('should set proper props to the oauth connect component', () => {
				expect(wrapper.find(oAuthButtonSelector).exists()).toBeTruthy()
				expect(wrapper.find(oAuthButtonSelector).props()).toMatchObject({
					isAdminConfigOk: false,
				})
			})
		})

		describe('user settings', () => {
			it('should be enabled if the admin has enabled the settings', async () => {
				await wrapper.setData({
					state: { user_name: 'test', token: '123', admin_config_ok: true, navigation_enabled: true, search_enabled: true, notification_enabled: true },
				})
				expect(wrapper.find(personalSettingsFormSelector)).toMatchSnapshot()
			})

			it('should be disabled if the admin has not enabled the settings', async () => {
				await wrapper.setData({
					state: {
						user_name: 'test',
						token: '123',
						admin_config_ok: true,
						navigation_enabled: false,
						search_enabled: false,
						notification_enabled: false,
					},
				})
				expect(wrapper.find(personalSettingsFormSelector)).toMatchSnapshot()
			})

			it('should send only one request if only one is enabled by the user', async () => {
				dialogs.showSuccess.mockImplementationOnce()
				const saveDefaultsSpy = jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({ data: [] }))
				const wrapper = getMountedWrapper({
					state: {
						user_name: 'test',
						token: '123',
						admin_config_ok: true,
						navigation_enabled: false,
						search_enabled: false,
						notification_enabled: false,
					},
				})

				let personalEnableNavigation = wrapper.find(personalEnableNavigationSelector)
				await personalEnableNavigation.trigger('click')
				personalEnableNavigation = wrapper.find(personalEnableNavigationSelector)
				expect(saveDefaultsSpy).toBeCalledTimes(1)
				expect(saveDefaultsSpy).toBeCalledWith(
					'http://localhost/apps/integration_openproject/config',
					{
						values: {
							navigation_enabled: '1',
						},
					}
				)
				expect(dialogs.showSuccess).toBeCalledTimes(1)
				expect(dialogs.showSuccess).toBeCalledWith('OpenProject options saved')
				jest.clearAllMocks()
			})

			it('admin and user should be able to enable and disable the setting simultaneously', async () => {
				dialogs.showSuccess.mockImplementationOnce()
				const saveDefaultsSpy = jest.spyOn(axios, 'put')
					.mockImplementationOnce(() => Promise.resolve({ data: [] }))
				const wrapper = getMountedWrapper({
					state: {
						user_name: 'test',
						token: '123',
						admin_config_ok: true,
						navigation_enabled: true,
						search_enabled: false,
						notification_enabled: true,
					},
				})

				let personalEnableSearch = wrapper.find(personalEnableSearchSelector)
				await personalEnableSearch.trigger('click')
				personalEnableSearch = wrapper.find(personalEnableSearchSelector)
				expect(saveDefaultsSpy).toBeCalledTimes(1)
				expect(saveDefaultsSpy).toBeCalledWith(
					'http://localhost/apps/integration_openproject/config',
					{
						values: {
							search_enabled: '1',
						},
					}
				)
				expect(dialogs.showSuccess).toBeCalledTimes(1)
				expect(dialogs.showSuccess).toBeCalledWith('OpenProject options saved')
				expect(wrapper.find(personalSettingsFormSelector)).toMatchSnapshot()
				await wrapper.setData({
					state: {
						navigation_enabled: false,
						search_enabled: true,
						notification_enabled: false,
					},
				})
				expect(wrapper.find(personalSettingsFormSelector)).toMatchSnapshot()
				jest.clearAllMocks()
			})
		})
	})
})

function getMountedWrapper(data = {}) {
	return mount(PersonalSettings, {
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
