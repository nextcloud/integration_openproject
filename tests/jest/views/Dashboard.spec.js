/* jshint esversion: 8 */

import { shallowMount, mount, createLocalVue } from '@vue/test-utils'
import Dashboard from '../../../src/views/Dashboard'
import axios from '@nextcloud/axios'
import * as initialState from '@nextcloud/initial-state'
import { STATE } from '../../../src/utils'
import notificationsResponse from '../fixtures/notificationsResponse.json'
import workPackagesSearchResponse
	from '../fixtures/workPackagesSearchResponse.json'
import * as dialogs from "@nextcloud/dialogs";

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
	const dashboardTriggerButtonSelector = '.trigger'
	const markAsReadButtonSelector = '.popover__wrapper .action-button'
	let wrapper
	beforeEach(() => {
		// eslint-disable-next-line no-import-assign
		initialState.loadState = jest.fn(() => true)

		// mock the beforeMount() method, so that the loop is not called automatically
		// we first need to mount the component, and the tests will call the loop themselves
		Dashboard.beforeMount = jest.fn()
	})
	it('should show the notification items in the Dashboard', async () => {
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
	describe('mark as read', () => {
		let axiosSpyGet
		beforeEach(() => {
			wrapper = mount(
				Dashboard,
				{
					localVue,
					attachTo: document.body,
					mocks: {
						t: (app, msg) => msg,
						generateUrl() {
							return '/'
						},
					},
					stubs: {
						Avatar: true,
					},
					propsData: {
						title: 'dashboard',
					},
				})
			axiosSpyGet = jest.spyOn(axios, 'get')
				.mockImplementation(() => Promise.resolve({
					data: [
						{
							_type: 'Notification',
							id: 47,
							readIAN: false,
							reason: 'assigned',
							createdAt: '2022-08-17T10:27:01Z',
							updatedAt: '2022-08-17T10:28:12Z',
							_links: {
								self: {
									href: '/api/v3/notifications/47',
								},
								readIAN: {
									href: '/api/v3/notifications/47/read_ian',
									method: 'post',
								},
								actor: {
									href: '/api/v3/users/8',
									title: 'Admin de DEV user',
								},
								project: {
									href: '/api/v3/projects/4',
									title: 'Dev-large',
								},
								activity: {
									href: '/api/v3/activities/261',
								},
								resource: {
									href: '/api/v3/work_packages/36',
									title: 'write a software',
								},
							},
						},
					],
				}))
		})
		afterEach(() => {
			axiosSpyGet.mockRestore()
		})
		it('should mark notifications as read', async () => {
			const axiosSpyDelete = jest.spyOn(axios, 'delete')
				.mockImplementationOnce(() => Promise.resolve({})
				)
			dialogs.showSuccess.mockImplementationOnce()
			await wrapper.vm.fetchNotifications()
			await localVue.nextTick()
			await wrapper.find(dashboardTriggerButtonSelector).trigger('click')
			await localVue.nextTick()
			await wrapper.find(markAsReadButtonSelector).trigger('click')
			await localVue.nextTick()
			expect(axiosSpyDelete).toHaveBeenCalledWith(
				'http://localhost/apps/integration_openproject/work-packages/36/notifications'
			)
			expect(dialogs.showSuccess).toHaveBeenCalledWith(
				'Notifications associated with Work package marked as read'
			)
			wrapper.destroy()
			axiosSpyDelete.mockRestore()
		})
		it('should show an error message if marking as read failed', async () => {
			const axiosSpyDelete = jest.spyOn(axios, 'delete')
				.mockRejectedValueOnce()
			dialogs.showError.mockImplementationOnce()
			await wrapper.vm.fetchNotifications()
			await localVue.nextTick()
			await wrapper.find(dashboardTriggerButtonSelector).trigger('click')
			await localVue.nextTick()
			await wrapper.find(markAsReadButtonSelector).trigger('click')
			await localVue.nextTick()
			expect(dialogs.showError).toHaveBeenCalledWith(
				'Failed to mark notifications as read'
			)
			wrapper.destroy()
			axiosSpyDelete.mockRestore()
		})
	})
})
