/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import PersonalSettings from '../../../src/components/PersonalSettings.vue'
import * as initialState from '@nextcloud/initial-state'

const localVue = createLocalVue()

describe('PersonalSettings.vue Test', () => {
	describe('oAuth', () => {
		const oAuthButtonSelector = 'oauthconnectbutton-stub'
		const oAuthDisconnectButtonSelector = '#openproject-rm-cred'
		const connectedAsLabenSelector = '.openproject-connected'
		const searchBlockSelector = '#openproject-search-block'
		let wrapper
		beforeEach(() => {
			// eslint-disable-next-line no-import-assign
			initialState.loadState = jest.fn(function() {
				return {
					request_url: 'https://localhost',
				}
			})
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
					state: cases,
				})
			})
			it('oAuth connect button is displayed', () => {
				expect(wrapper.find(oAuthButtonSelector).exists()).toBeTruthy()
			})
			it('search settings are not displayed', () => {
				expect(wrapper.find(searchBlockSelector).exists()).toBeFalsy()
			})
			it('oAuth disconnect button is not displayed', () => {
				expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeFalsy()
			})
		})
		describe('when username and token are given', () => {
			beforeEach(async () => {
				await wrapper.setData({
					state: { user_name: 'test', token: '123' },
				})
			})
			it('oAuth connect button is not displayed', () => {
				expect(wrapper.find(oAuthButtonSelector).exists()).toBeFalsy()
			})
			it('oAuth disconnect button is displayed', () => {
				expect(wrapper.find(oAuthDisconnectButtonSelector).exists()).toBeTruthy()
			})
			it('connected as label is displayed', () => {
				expect(wrapper.find(connectedAsLabenSelector).text()).toBe('Connected as {user}')
			})
			it('oAuth search settings are displayed', () => {
				expect(wrapper.find(searchBlockSelector).exists()).toBeTruthy()
			})
		})
	})
})
