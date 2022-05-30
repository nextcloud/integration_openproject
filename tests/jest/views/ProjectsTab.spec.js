/* jshint esversion: 8 */

import { shallowMount, mount, createLocalVue } from '@vue/test-utils'
import ProjectsTab from '../../../src/views/ProjectsTab'
import axios from '@nextcloud/axios'
import * as initialState from '@nextcloud/initial-state'
import { STATE } from '../../../src/utils'
import workPackagesSearchResponse from '../fixtures/workPackagesSearchResponse.json'

jest.mock('@nextcloud/dialogs')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

global.OC = {
	dialogs: {
		confirmDestructive: jest.fn(),
		YES_NO_BUTTONS: 70,
	},
}

jest.mock('@nextcloud/axios')
const localVue = createLocalVue()

describe('ProjectsTab.vue Test', () => {
	let wrapper
	const loadingIndicatorSelector = '.icon-loading'
	const emptyContentSelector = '#openproject-empty-content'
	const workPackagesSelector = '#openproject-linked-workpackages'
	const existingRelationSelector = '.existing-relations'
	const searchInputStubSelector = 'searchinput-stub'
	const linkedWorkpackageSelector = '.workpackage'
	const workPackageUnlinkSelector = '.linked-workpackages--workpackage--unlink'

	beforeEach(() => {
		// eslint-disable-next-line no-import-assign
		initialState.loadState = jest.fn(() => 'https://openproject/oauth/')
		wrapper = shallowMount(ProjectsTab, { localVue })
	})
	describe('search input existence', () => {
		it('should not exist if the request url is not valid', async () => {
			await wrapper.setData({
				requestUrl: false,
			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeFalsy()
		})
		it('should not exist if the wrapper is in "loading" state', async () => {
			await wrapper.setData({
				requestUrl: true,
				state: STATE.LOADING,
			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeFalsy()
		})
		it('should exist if the request url is valid and wrapper is not "loading"', async () => {
			await wrapper.setData({
				requestUrl: 'https://open.project/',
				state: STATE.OK,

			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeTruthy()
		})
	})
	describe('loading icon', () => {
		it('shows the loading icon during "loading" state', async () => {
			wrapper.setData({ state: STATE.LOADING })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
		})
		it('does not show the empty content message during "loading" state', async () => {
			wrapper.setData({ state: STATE.LOADING })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeFalsy()
		})
		it.each([STATE.OK, STATE.ERROR])('makes the loading icon disappear on state change', async (state) => {
			wrapper.setData({ state: STATE.LOADING })
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
			wrapper.setData({ state: STATE.OK })
			expect(wrapper.vm.state).toBe(STATE.OK)
			wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(STATE.LOADING)
		})
	})
	describe('empty message', () => {
		it.each([STATE.NO_TOKEN, STATE.ERROR, STATE.OK])('shows the empty message when state is other than loading', async (state) => {
			wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeTruthy()
		})
	})
	describe('fetchWorkpackages', () => {
		it.each([
			{ HTTPStatus: 400, AppState: STATE.FAILED_FETCHING_WORKPACKAGES },
			{ HTTPStatus: 401, AppState: STATE.NO_TOKEN },
			{ HTTPStatus: 402, AppState: STATE.FAILED_FETCHING_WORKPACKAGES },
			{ HTTPStatus: 404, AppState: STATE.CONNECTION_ERROR },
			{ HTTPStatus: 500, AppState: STATE.ERROR },
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
			expect(wrapper.vm.state).toBe(STATE.FAILED_FETCHING_WORKPACKAGES)
		})
		it('sets the "ok" state on empty response', async () => {
			axios.get
				.mockImplementation(() => Promise.resolve({ status: 200, data: [] }))
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(STATE.OK)
		})
		it('sets the "connection-error" state if the request url is not valid', async () => {
			const wrapper = mountWrapper()
			axios.get
				.mockImplementation(() => Promise.resolve({ status: 200, data: [] }))
			await wrapper.setData({
				requestUrl: false,
			})
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(STATE.CONNECTION_ERROR)
			expect(wrapper).toMatchSnapshot()
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
			expect(wrapper.vm.state).toBe(STATE.OK)
			const workPackages = wrapper.find(workPackagesSelector)
			expect(wrapper.find(existingRelationSelector).exists()).toBeTruthy()
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages).toMatchSnapshot()
		})
		it('adds every work-package only once', async () => {
			// this can happen if multiple replies arrive at the same time
			// when the user switches between files while results still loading
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
					}],
				}))
				.mockImplementation(() => Promise.resolve({
					status: 200,
					data: [],
				}))
			await wrapper.vm.update({ id: 2222 })
			expect(axiosGetSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/work-packages?fileId=2222',
				{}
			)
			expect(wrapper.vm.state).toBe(STATE.OK)
			const workPackages = wrapper.find(workPackagesSelector)
			expect(workPackages).toMatchSnapshot()
		})
	})
	describe('onSave', () => {
		it('shows the just linked workpackage', async () => {
			wrapper = mountWrapper()
			await wrapper.setData({
				fileInfo: { id: 1234 },
			})
			await localVue.nextTick()
			await wrapper.vm.onSaved(workPackagesSearchResponse[0])
			const workPackages = wrapper.find(workPackagesSelector)
			expect(wrapper.find(existingRelationSelector).exists()).toBeTruthy()
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages).toMatchSnapshot()
		})
	})
	describe('when the work package is clicked', () => {
		it('opens work package in open project', async () => {
			axios.get
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: 'http://openproject',
				}))
			window.open = jest.fn()
			wrapper = mountWrapper()
			await wrapper.setData({
				workpackages: workPackagesSearchResponse,
				fileInfo: { id: 1234 },
			})
			await localVue.nextTick()
			await wrapper.find(linkedWorkpackageSelector).trigger('click')
			await localVue.nextTick()
			expect(window.open).toHaveBeenCalledTimes(1)
			expect(window.open).toHaveBeenCalledWith(
				'http://openproject/projects/15/work_packages/1'
			)
		})
	})
	describe('when workpackage unlink button is clicked', () => {
		it('should display a confirmation dialog box', async () => {
			wrapper = mountWrapper()
			await wrapper.setData({
				workpackages: workPackagesSearchResponse,
				fileInfo: { id: 1234 },
			})
			await localVue.nextTick()
			await expect(wrapper.find(workPackageUnlinkSelector).exists()).toBeTruthy()
			await wrapper.find(workPackageUnlinkSelector).trigger('click')
			await localVue.nextTick()
			expect(OC.dialogs.confirmDestructive).toHaveBeenCalledTimes(1)
			expect(OC.dialogs.confirmDestructive).toHaveBeenCalledWith(
				'Are you sure you want to unlink the work package?',
				'Confirm unlink',
				{ cancel: 'Cancel', confirm: 'Unlink', confirmClasses: 'error', type: 70 },
				expect.any(Function),
				true
			)
		})
	})
	describe('unlinkWorkPackage', () => {
		it('should unlink the work package', async () => {
			const axiosGetSpy = jest.spyOn(axios, 'get')
				.mockImplementationOnce(() => Promise.resolve({
					status: 200,
					data: [{
						_type: 'FileLink',
						id: 66,
						createdAt: '2022-04-06T05:14:24Z',
						updatedAt: '2022-04-06T05:14:24Z',
						originData: {
							id: '6',
							name: 'welcome.txt',
							mimeType: 'text/plain',
							createdAt: '1970-01-01T00:00:00Z',
							lastModifiedAt: '2022-03-30T07:39:56Z',
							createdByName: '',
							lastModifiedByName: '',
						},
						_links: {
							delete: {
								href: '/api/v3/file_links/66',
								method: 'delete',
							},
						},
					}],
				}))
			const axiosDeleteSpy = jest.spyOn(axios, 'delete').mockImplementationOnce(() => Promise.resolve(
				{ status: 200 })
			)
			wrapper = mountWrapper()
			await wrapper.vm.unlinkWorkPackage(15, 6)
			expect(axiosGetSpy).toBeCalledWith(
				'http://localhost/apps/integration_openproject/work-packages/15/file-links'
			)
			expect(axiosDeleteSpy).toBeCalledWith('http://localhost/apps/integration_openproject/file-links/66')
			axiosGetSpy.mockRestore()
			axiosDeleteSpy.mockRestore()
		})

		it.each([
			{ HTTPStatus: 401, state: 'no-token' },
			{ HTTPStatus: 404, state: 'error' },
			{ HTTPStatus: 500, state: 'error' },
		])('sets states according to HTTP error codes', async (cases) => {
			const err = new Error()
			err.response = { status: cases.HTTPStatus }
			axios.get.mockRejectedValueOnce(err)
			wrapper = mountWrapper()

			try {
				wrapper.vm.unlinkWorkPackage(15, 6)
			} catch (error) {
				expect(wrapper.vm.state).toBe(cases.state)
				expect(error).toBe({ Error: 'could not fetch the delete link of work-package' })
			}

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
			EmptyContent: false,
		},
		data: () => ({
			error: '',
			state: STATE.OK,
			fileInfo: {},
			workpackages: [],
			requestUrl: 'something',
		}),
	})
}
