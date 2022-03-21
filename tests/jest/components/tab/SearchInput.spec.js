/* jshint esversion: 8 */
import axios from '@nextcloud/axios'
import { createLocalVue, mount } from '@vue/test-utils'
import * as dialogs from '@nextcloud/dialogs'

import SearchInput from '../../../../src/components/tab/SearchInput'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'
import workPackagesSearchResponseNoAssignee from '../../fixtures/workPackagesSearchResponseNoAssignee.json'

jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/dialogs')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

const localVue = createLocalVue()

describe('SearchInput.vue tests', () => {
	let wrapper

	const stateSelector = '.stateMsg'
	const searchListSelector = '.workpackage'
	const inputSelector = '#search-input'
	const assigneeSelector = '.filterAssignee'
	const loadingIconSelector = '.icon-loading-small'
	const multiSelectItemSelector = '.multiselect__option'

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
					searchResults: [{
						someData: 'someData',
					}],
				})

				await inputField.setValue('org')

				expect(wrapper.vm.searchResults).toMatchObject([])
			})
			it.each([
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
				wrapper = mountSearchInput()
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue(search)
				expect(axiosSpy).toHaveBeenCalledTimes(expectedCallCount)
				axiosSpy.mockRestore()
			})
			it('should include the search text in the search payload', async () => {
				const axiosSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
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
					},
				)
				axiosSpy.mockRestore()
			})
			it('should log an error on invalid payload', async () => {
				const axiosSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [{
							id: 123,
						}],
					}))
				const consoleMock = jest.spyOn(console, 'error')
					.mockImplementationOnce(() => {})
				wrapper = mountSearchInput()
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')
				await localVue.nextTick()
				expect(consoleMock).toHaveBeenCalledWith('could not process workpackage data')
				consoleMock.mockRestore()
				axiosSpy.mockRestore()
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

		describe('click on a workpackage option', () => {
			let axiosGetSpy
			beforeEach(async () => {
				axiosGetSpy = jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
				wrapper = mountSearchInput({ id: 111, name: 'file.txt' })
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')
				await wrapper.setData({
					searchResults: [{
						id: 999,
					}],
				})
			})
			afterEach(() => {
				axiosGetSpy.mockRestore()
			})
			it('should emit an action', async () => {
				const multiselectItem = wrapper.find(multiSelectItemSelector)
				await multiselectItem.trigger('click')
				const savedEvent = wrapper.emitted('saved')
				expect(savedEvent).toHaveLength(1)
				expect(savedEvent[0]).toEqual([{ id: 999 }])
			})
			it('should send a request to link file to workpackage', async () => {
				const postSpy = jest.spyOn(axios, 'post')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
					}))
				const multiselectItem = wrapper.find(multiSelectItemSelector)
				await multiselectItem.trigger('click')
				const expectedParams = new URLSearchParams()
				expectedParams.append('workpackageId', 999)
				expectedParams.append('fileId', 111)
				expectedParams.append('fileName', 'file.txt')
				expect(postSpy).toBeCalledWith(
					'http://localhost/apps/integration_openproject/work-packages',
					expectedParams,
					{ headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }
				)
				postSpy.mockRestore()
			})
			it('should show an error when linking failed', async () => {
				const err = new Error()
				err.response = { status: 422 }
				axios.post.mockRejectedValueOnce(err)
				const showErrorSpy = jest.spyOn(dialogs, 'showError')
				const multiselectItem = wrapper.find(multiSelectItemSelector)
				await multiselectItem.trigger('click')
				await localVue.nextTick()
				expect(showErrorSpy).toBeCalledTimes(1)
				showErrorSpy.mockRestore()
			})
		})

		describe('fileInfo prop', () => {
			it('should reset the input state when the prop is changed', async () => {
				jest.spyOn(axios, 'get')
					.mockImplementationOnce(() => Promise.resolve({
						status: 200,
						data: [],
					}))
				wrapper = mountSearchInput({ id: 111, name: 'file.txt' })
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')
				const spyDocument = jest.spyOn(document, 'getElementById')
					.mockImplementationOnce(() => ({
						value: 'orga',
					}))
				await wrapper.setProps({
					fileInfo: { id: 222, name: 'file2.txt' },
				})
				expect(spyDocument).toBeCalledWith('search-input')
				expect(wrapper.vm.selectedId).toMatchObject([])
				expect(wrapper.vm.searchResults).toMatchObject([])
				expect(wrapper.vm.state).toBe('ok')
			})
		})
	})
})

function mountSearchInput(fileInfo = {}) {
	return mount(SearchInput, {
		localVue,
		mocks: {
			t: (msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		stubs: {
			Avatar: true,
		},
		propsData: {
			fileInfo,
		},
	})
}
