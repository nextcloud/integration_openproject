/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import PersonalSettings from '../../../src/components/PersonalSettings.vue'

const localVue = createLocalVue()

describe('PersonalSettings.vue Test', () => {
	describe('oAuth', () => {
		const oAuthSelector = '#openproject-oauth'
		let wrapper
		beforeEach(() => {
			jest.mock('@nextcloud/initial-state')
			jest.mock('@nextcloud/router')

			wrapper = shallowMount(PersonalSettings, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
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
})
