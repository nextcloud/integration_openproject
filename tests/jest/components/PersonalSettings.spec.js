/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import PersonalSettings from '../../../src/components/PersonalSettings.vue'

import * as initialState from '@nextcloud/initial-state'
const localVue = createLocalVue()

describe('PersonalSettings.vue Test', () => {
	const oAuthSelector = '#openproject-oauth'
	const individualConnectionSelector = '#individual_connection'
	describe('oAuth', () => {
		let wrapper
		beforeEach(() => {
			// eslint-disable-next-line no-import-assign
			initialState.loadState = jest.fn(function() {
				return {
					url: 'https://localhost',
					oauth_instance_url: 'https://localhost',
					client_id: '123',
					client_secret: '123',
					allow_individual_connection: true,
				}
			})
			wrapper = createWrapper()
		})
		it('oAuth is used when url === oauth_instance_url and client_id & client_secret are set', () => {
			expect(wrapper.find(oAuthSelector).exists()).toBeTruthy()
		})
		it.each([
			{ url: 'https://localhost/', oauth_instance_url: 'https://localhost' },
			{ url: 'https://localhost:3000/', oauth_instance_url: 'https://localhost:3000' },
			{ url: 'https://localhost//', oauth_instance_url: 'https://localhost' },
			{ url: 'https://localhost//', oauth_instance_url: 'https://localhost/' },
			{ url: 'https://localhost', oauth_instance_url: 'https://localhost/' },
			{ url: 'https://localhost', oauth_instance_url: 'https://localhost//' },
			{ url: 'https://localhost/', oauth_instance_url: 'https://localhost//' },
			{ url: 'http://server.np/openproject', oauth_instance_url: 'http://server.np/openproject/' },
		])('oAuth is used when url and oauth_instance_url are different in an insignificant way', async (
			cases) => {
			await wrapper.setData({
				state: {
					url: cases.url,
					oauth_instance_url: cases.oauth_instance_url,
				},
			})
			expect(wrapper.find(oAuthSelector).exists()).toBeTruthy()
		})
		it.each([
			{ url: 'https://differnt', oauth_instance_url: 'https://localhost' },
			{ url: 'http://localhost', oauth_instance_url: 'https://localhost' },
			{ url: 'http://openproject.it', oauth_instance_url: 'https://openproject.de' },
			{ url: 'https://openproject.it:8081', oauth_instance_url: 'https://openproject.de:8080' },
			{ url: 'http://server.np/OpenProject', oauth_instance_url: 'http://server.np/openproject' },
		])('oAuth is not used when url and oauth_instance_url are different', async (
			cases) => {
			await wrapper.setData({
				state: {
					url: cases.url,
					oauth_instance_url: cases.oauth_instance_url,
				},
			})
			expect(wrapper.find(oAuthSelector).exists()).toBeFalsy()
		})
		it.each(['', null, undefined, 0, false])('oAuth is not used when client_id is not set', async (clientId) => {
			await wrapper.setData({
				state: {
					client_id: clientId,
				},
			})
			expect(wrapper.find(oAuthSelector).exists()).toBeFalsy()
		})
		it.each(['', null, undefined, 0, false])('oAuth is not used when client_secret is not set', async (clientSecret) => {
			await wrapper.setData({
				state: {
					client_secret: clientSecret,
				},
			})
			expect(wrapper.find(oAuthSelector).exists()).toBeFalsy()
		})
	})
	describe('individual connections forbidden', () => {
		let wrapper
		beforeEach(() => {
			// eslint-disable-next-line no-import-assign
			initialState.loadState = jest.fn(function() {
				return {
					url: 'https://localhost',
					oauth_instance_url: 'https://different',
					client_id: '123',
					client_secret: '123',
					allow_individual_connection: false,
				}
			})
			wrapper = createWrapper()
		})
		it('oAuth is used when url and oauth_instance_url are different', () => {
			expect(wrapper.find(oAuthSelector).exists()).toBeTruthy()
		})
		it('no settings for individual connections are displayed', () => {
			expect(wrapper.find(individualConnectionSelector).exists()).toBeFalsy()
		})
	})
})

function createWrapper() {
	return shallowMount(PersonalSettings, {
		localVue,
		mocks: {
			t: (msg) => msg,
			generateUrl() {
				return '/'
			},
		},
	})
}
