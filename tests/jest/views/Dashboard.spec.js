/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import Dashboard from '../../../src/views/Dashboard'
import axios from '@nextcloud/axios'
import * as initialState from '@nextcloud/initial-state'
import { STATE } from '../../../src/utils'
import notificationsResponse from '../fixtures/notificationsResponse.json'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(),
}))
jest.mock('@nextcloud/dialogs', () => ({
	getLanguage: jest.fn(() => ''),
	showError: jest.fn(),
	showSuccess: jest.fn(),
}))

global.OCA = {}
global.OC = {}
const localVue = createLocalVue()

describe('Dashboard.vue', () => {
	let wrapper
	beforeEach(() => {
		// eslint-disable-next-line no-import-assign
		initialState.loadState = jest.fn(() => true)

		// mock the beforeMount() method, so that the loop is not called automatically
		// we first need to mount the component, and the tests will call the loop themselves
		Dashboard.beforeMount = jest.fn()
		wrapper = shallowMount(
			Dashboard,
			{
				localVue,
				mocks: {
					t: (app, msg) => msg,
					generateUrl() {
						return '/'
					},
				},
				propsData: {
					title: 'dashboard',
				},
			})
	})
	it('should show the notification items in the Dashboard', async () => {
		const axiosSpy = jest.spyOn(axios, 'get')
			.mockImplementationOnce(() => Promise.resolve({ data: 'http://openproject.org' }))
			.mockImplementationOnce(() => Promise.resolve({ data: notificationsResponse }))
		await wrapper.vm.launchLoop()
		expect(axiosSpy).toBeCalledWith(
			'http://localhost/apps/integration_openproject/url',
		)
		expect(axiosSpy).toBeCalledWith(
			'http://localhost/apps/integration_openproject/notifications',
		)
		expect(wrapper.vm.state).toBe(STATE.OK)
		expect(wrapper.vm.items).toStrictEqual([
			{
				id: '17',
				targetUrl: 'http://openproject.org/notifications/details/17/activity/',
				avatarUrl: 'http://localhost/apps/integration_openproject/avatar?userId=8&userName=Admin de DEV user',
				avatarUsername: 'Admin de DEV userz',
				mainText: '(5) Create wireframes for new landing page',
				subText: 'Scrum project - assigned,mentioned',
				overlayIconUrl: '',
			},
			{
				id: '18',
				targetUrl: 'http://openproject.org/notifications/details/18/activity/',
				avatarUrl: 'http://localhost/apps/integration_openproject/avatar?userId=9&userName=Artur Neumann',
				avatarUsername: 'Artur Neumannz',
				mainText: '(1) Contact form',
				subText: 'Scrum project - mentioned',
				overlayIconUrl: '',
			},
			{
				id: '36',
				targetUrl: 'http://openproject.org/notifications/details/36/activity/',
				avatarUrl: 'http://localhost/apps/integration_openproject/avatar?userId=8&userName=Admin de DEV user',
				avatarUsername: 'Admin de DEV userz',
				mainText: '(2) write a software',
				subText: 'Dev-large - assigned',
				overlayIconUrl: '',
			},
		])
		axiosSpy.mockRestore()
	})
})
