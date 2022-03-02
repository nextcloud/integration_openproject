/* jshint esversion: 8 */
import axios from '@nextcloud/axios'
import { createLocalVue, mount } from '@vue/test-utils'

import SearchInput from '../../../../src/components/tab/SearchInput'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'
import workPackagesSearchResponseNoAssignee from '../../fixtures/workPackagesSearchResponseNoAssignee.json'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

const localVue = createLocalVue()

describe('SearchInput.vue tests', () => {
	let wrapper

	const stateSelector = '.stateMsg'
<<<<<<< HEAD
	const searchListSelector = '.searchList'
=======
	const searchListSelector = '.workpackage__workPackage'
>>>>>>> UI redesign for WorkPackages
	const inputSelector = '.multiselect__input'
	const assigneeSelector = '.filterAssignee'
	const loadingIconSelector = '.icon-loading-small'

	afterEach(() => {
		wrapper.destroy()
		jest.clearAllMocks()
		jest.restoreAllMocks()
	})

	describe('state messages', () => {
		it.each(['no-token', 'error', 'any'])('%s: should display the correct state message', async (state) => {
			wrapper = mountSearchInput()
			await wrapper.setData({ state })
			expect(wrapper.find(stateSelector)).toMatchSnapshot()
		})
	})

	describe('work packages multiselect', () => {
		describe('search input', () => {
			it('should reset the state if search value length becomes lesser than search char limit', async () => {
				wrapper = mountSearchInput()
				const inputField = wrapper.find(inputSelector)
				await wrapper.setData({
<<<<<<< HEAD
					searchResults: ['someData'],
=======
					searchResults: [{
						someData: 'someData',
					}],
>>>>>>> UI redesign for WorkPackages
				})

				await inputField.setValue('org')

				expect(wrapper.vm.searchResults).toMatchObject([])
			})
			it.each([
<<<<<<< HEAD
				{ search: 'o', expectedCallCount: 0 },
				{ search: 'or', expectedCallCount: 0 },
				{ search: 'org', expectedCallCount: 0 },
				{ search: 'orga', expectedCallCount: 1 },
			])('should send search request only if the search text is greater than search threshold', async ({ search, expectedCallCount }) => {
				const axiosSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({ status: 200, data: [] }))
=======
				{
					search: 'o',
					expectedCallCount: 0,
				},
				{
					search: 'or',
					expectedCallCount: 0,
				},
				{
					search: 'org',
					expectedCallCount: 0,
				},
				{
					search: 'orga',
					expectedCallCount: 1,
				},
			])('should send search request only if the search text is greater than search threshold', async ({
				search,
				expectedCallCount,
			}) => {
				const axiosSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
>>>>>>> UI redesign for WorkPackages
				wrapper = mountSearchInput()
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue(search)
				expect(axiosSpy).toHaveBeenCalledTimes(expectedCallCount)
			})
			it('should include the search text in the search payload', async () => {
				const axiosSpy = jest
					.spyOn(axios, 'get')
<<<<<<< HEAD
					.mockImplementationOnce(() => Promise.resolve({ status: 200, data: [] }))
=======
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
>>>>>>> UI redesign for WorkPackages
				wrapper = mountSearchInput()
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')

				expect(axiosSpy).toHaveBeenCalledTimes(1)
				expect(axiosSpy).toHaveBeenCalledWith(
					expect.stringContaining('work-packages'),
					{
						params: {
							searchQuery: 'orga',
						},
<<<<<<< HEAD
					}
=======
					},
>>>>>>> UI redesign for WorkPackages
				)
			})
		})

		describe('search list', () => {
			beforeEach(() => {
				wrapper = mountSearchInput()
			})
			it('should not be displayed if the search results is empty', async () => {
				await wrapper.setData({
					searchResults: [],
				})
				const searchList = wrapper.find(searchListSelector)
				expect(searchList.exists()).toBeFalsy()
			})
			it('should display correct options list of search results', async () => {
				await wrapper.setData({
					searchResults: workPackagesSearchResponse,
				})
				const searchList = wrapper.find(searchListSelector)

				expect(searchList.exists()).toBeTruthy()
				expect(searchList).toMatchSnapshot()
			})
			it('should not display the "avatar" and "name" if the "assignee" is not present in a work package', async () => {
				await wrapper.setData({
					searchResults: workPackagesSearchResponseNoAssignee,
				})
				const assignee = wrapper.find(assigneeSelector)
				expect(assignee.exists()).toBeFalsy()
			})
		})

		describe('loading icon', () => {
			it('should be displayed when the wrapper is in "loading" state', async () => {
				wrapper = mountSearchInput()
				let loadingIcon = wrapper.find(loadingIconSelector)
				expect(loadingIcon.exists()).toBeFalsy()
				await wrapper.setData({
					state: 'loading',
				})
				loadingIcon = wrapper.find(loadingIconSelector)
				expect(loadingIcon.exists()).toBeTruthy()
			})
		})
	})
})

function mountSearchInput() {
	return mount(SearchInput, {
		localVue,
		mocks: {
			t: (msg) => msg,
			generateUrl() {
				return '/'
<<<<<<< HEAD
			},
		},
		stubs: {
			Avatar: true,
		},
=======
			},
		},
		stubs: {
			Avatar: true,
		},
		propsData: {
			fileInfo: {
				type: Object,
				required: true,
			},
		},
>>>>>>> UI redesign for WorkPackages
	})
}
