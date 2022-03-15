/* jshint esversion: 8 */

import { shallowMount, mount, createLocalVue } from '@vue/test-utils'
import ProjectsTab from '../../../src/views/ProjectsTab'
import axios from '@nextcloud/axios'
import * as initialState from '@nextcloud/initial-state'
import workPackagesSearchResponse from '../fixtures/workPackagesSearchResponse.json'

jest.mock('@nextcloud/axios')
const localVue = createLocalVue()

describe('ProjectsTab.vue Test', () => {
	let wrapper
	const loadingIndicatorSelector = '.icon-loading'
	const emptyContentSelector = '#openproject-empty-content'
	const workPackagesSelector = '#openproject-linked-workpackages'
	const existingRelationSelector = '.existing-relations'

	beforeEach(() => {
		// eslint-disable-next-line no-import-assign
		initialState.loadState = jest.fn(() => 'https://openproject/oauth/')
		wrapper = shallowMount(ProjectsTab, {
			localVue,
			data: () => ({
				fileInfo: {},
			}),
		})
	})
	describe('loading icon', () => {
		it('shows the loading icon during "loading" state', async () => {
			wrapper.setData({ state: 'loading' })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
		})
		it('does not show the empty content message during "loading" state', async () => {
			wrapper.setData({ state: 'loading' })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeFalsy()
		})
		it.each(['ok', 'error'])('makes the loading icon disappear on state change', async (state) => {
			wrapper.setData({ state: 'loading' })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
			wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeFalsy()
		})
		it('sets the state to "loading" on update', async () => {
			const err = new Error()
			err.response = { status: 404 }
			axios.get.mockRejectedValueOnce(err)
			wrapper.setData({ state: 'ok' })
			expect(wrapper.vm.state).toBe('ok')
			wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe('loading')
		})
	})
	describe('empty message', () => {
		it.each(['no-token', 'ok', 'error'])('shows the empty message when state is other than loading', async (state) => {
			wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeTruthy()
		})
	})
	describe('fetchWorkpackages', () => {
		it.each([
			{ HTTPStatus: 400, AppState: 'failed-fetching-workpackages' },
			{ HTTPStatus: 401, AppState: 'no-token' },
			{ HTTPStatus: 402, AppState: 'failed-fetching-workpackages' },
			{ HTTPStatus: 404, AppState: 'connection-error' },
			{ HTTPStatus: 500, AppState: 'error' },
		])('sets states according to HTTP error codes', async (cases) => {
			const err = new Error()
			err.response = { status: cases.HTTPStatus }
			axios.get.mockRejectedValueOnce(err)
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(cases.AppState)
		})
		it.each([
			null,
			'string',
			undefined,
			[{ // missing id
				subject: 'subject',
				_links: {
					status: {
						href: '/api/v3/statuses/12',
						title: 'open',
					},
					type: {
						href: '/api/v3/types/6',
						title: 'Task',
					},
					assignee: {
						href: '/api/v3/users/1',
						title: 'Bal Bahadur Pun',
					},
					project: { title: 'a big project' },
				},
			}],
			[{ // empty subject
				id: 123,
				subject: '',
				_links: {
					status: {
						href: '/api/v3/statuses/12',
						title: 'open',
					},
					type: {
						href: '/api/v3/types/6',
						title: 'Task',
					},
					assignee: {
						href: '/api/v3/users/1',
						title: 'Bal Bahadur Pun',
					},
					project: { title: 'a big project' },
				},
			}],
			[{ // missing subject
				id: 123,
				_links: {
					status: {
						href: '/api/v3/statuses/12',
						title: 'open',
					},
					type: {
						href: '/api/v3/types/6',
						title: 'Task',
					},
					assignee: {
						href: '/api/v3/users/1',
						title: 'Bal Bahadur Pun',
					},
					project: { title: 'a big project' },
				},
			}],
			[{ // missing _links.status.title
				id: 123,
				subject: 'my task',
				_links: {
					status: {
						href: '/api/v3/statuses/12',
					},
					type: {
						href: '/api/v3/types/6',
						title: 'Task',
					},
					assignee: {
						href: '/api/v3/users/1',
						title: 'Bal Bahadur Pun',
					},
					project: { title: 'a big project' },
				},
			}],
			[{ // missing project.title
				id: 123,
				subject: 'my task',
				_links: {
					status: {
						href: '/api/v3/statuses/12',
						title: 'open',
					},
					type: {
						href: '/api/v3/types/6',
						title: 'Task',
					},
					assignee: {
						href: '/api/v3/users/1',
						title: 'Bal Bahadur Pun',
					},
					project: { },
				},
			}],
		])('sets the "failed-fetching-workpackages" state on invalid responses', async (testCase) => {
			axios.get
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: testCase,
				}))
				// mock for color requests, it should not fail because of the missing mock
				.mockImplementation(() => Promise.resolve({ status: 200, data: [] }))
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe('failed-fetching-workpackages')
		})
		it('sets the "ok" state on empty response', async () => {
			axios.get
				.mockImplementation(() => Promise.resolve({ status: 200, data: [] }))
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe('ok')
		})
		it.each([
			{ statusColor: { color: '#A5D8FF' }, typeColor: { color: '#00B0F0' } },
			{ statusColor: { }, typeColor: { } },
		])('shows the linked workpackages', async (testCase) => {
			wrapper = mountWrapper()
			const axiosGetSpy = jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: [{
						id: 123,
						subject: 'my task',
						_links: {
							status: {
								href: '/api/v3/statuses/12',
								title: 'open',
							},
							type: {
								href: '/api/v3/types/6',
								title: 'Task',
							},
							assignee: {
								href: '/api/v3/users/1',
								title: 'Bal Bahadur Pun',
							},
							project: { title: 'a big project' },
						},
					},
					{
						id: 589,
						subject: 'नेपालमा IT उद्योग बनाउने',
						_links: {
							status: {
								href: '/api/v3/statuses/2',
								title: 'प्रगति हुदैछ',
							},
							type: {
								href: '/api/v3/types/16',
								title: 'Epic',
							},
							assignee: {
								href: '/api/v3/users/13',
								title: 'कुमारी नेपाली',
							},
							project: { title: 'नेपालको विकास गर्ने' },
						},
					}],
				}))
				// mock for color requests
				.mockImplementationOnce(() => Promise.resolve(
					{ status: 200, data: testCase.statusColor })
				)
				.mockImplementationOnce(() => Promise.resolve(
					{ status: 200, data: testCase.typeColor })
				)
			await wrapper.vm.update({ id: 789 })
			expect(axiosGetSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/work-packages?fileId=789',
				{}
			)
			expect(axiosGetSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/statuses/12',
			)
			expect(axiosGetSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/types/6',
			)
			expect(wrapper.vm.state).toBe('ok')
			const workPackages = wrapper.find(workPackagesSelector)
			expect(wrapper.find(existingRelationSelector).exists()).toBeTruthy()
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages).toMatchSnapshot()
		})
	})
	describe('onSave', () => {
		it('shows the just linked workpackage', async () => {
			wrapper = mountWrapper()
			await wrapper.vm.onSaved(workPackagesSearchResponse[0])
			const workPackages = wrapper.find(workPackagesSelector)
			expect(wrapper.find(existingRelationSelector).exists()).toBeTruthy()
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages).toMatchSnapshot()
		})
	})
})

function mountWrapper() {
	return mount(ProjectsTab, {
		localVue,
		mocks: {
			t: (msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		stubs: {
			SearchInput: true,
			Avatar: true,
		},
		data: () => ({
			error: '',
			state: 'ok',
			fileInfo: {},
			workpackages: [],
			requestUrl: 'something',
		}),
	})
}
