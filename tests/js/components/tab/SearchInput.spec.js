/* jshint esversion: 8 */

/**
 * SPDX-FileCopyrightText: 2022-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2022-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'
import flushPromises from 'flush-promises'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import * as dialogs from '@nextcloud/dialogs'

import SearchInput from '../../../../src/components/tab/SearchInput.vue'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'
import workPackagesSearchResponseNoAssignee from '../../fixtures/workPackagesSearchResponseNoAssignee.json'
import workPackageSearchReqResponse from '../../fixtures/workPackageSearchReqResponse.json'
import workPackageObjectsInSearchResults from '../../fixtures/workPackageObjectsInSearchResults.json'
import { STATE, WORKPACKAGES_SEARCH_ORIGIN } from '../../../../src/utils.js'
import { workpackageHelper } from '../../../../src/utils/workpackageHelper.js'
import { toMatchSerializedSnapshot } from '../../utils.js'

vi.mock(import('@nextcloud/axios'), async (importOriginal) => {
	const originalModule = await importOriginal()
	return {
		__esModule: true,
		...originalModule,
		default: {
			get: vi.fn(),
			put: vi.fn(),
			post: vi.fn(),
		},
	}
})
vi.mock(import('@nextcloud/dialogs'), () => ({
	getLanguage: vi.fn(() => ''),
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))
vi.mock(import('lodash/debounce'), () => ({
	default: (fn) => {
		fn.cancel = vi.fn()
		return fn
	},
}))
vi.mock(import('@nextcloud/initial-state'), async (importOriginal) => {
	const originalModule = await importOriginal()
	return {
		__esModule: true,
		...originalModule,
		default: vi.fn(),
		loadState: vi.fn(() => {
			return {
				openproject_instance_url: null,
				version: '32',
			}
		}),
	}
})

const simpleWorkPackageSearchResponse = [{
	id: 1,
	subject: 'some subject',
	_links: {
		assignee: {
			title: 'some assignee',
			href: 'http://href/0/',
		},
		status: {
			title: 'some status',
			href: 'http://href/1/',
		},
		type: {
			title: 'some type',
			href: 'http://href/2/',
		},
		project: {
			title: 'some project',
			href: 'http://href/3/',
		},
	},
}]

// url
const avatarUrl = generateOcsUrl('/apps/integration_openproject/api/v1/avatar?userId=1&userName=System')
const workPackageUrl = generateOcsUrl('/apps/integration_openproject/api/v1/work-packages')

describe('SearchInput.vue', () => {
	let wrapper

	const stateSelector = '.stateMsg'
	const workpackagesListSelector = '[role="listbox"]'
	const workPackageStubSelector = 'work-package-stub'
	const inputSelector = '.searchInput input'
	const assigneeSelector = '.filterAssignee'
	const loadingIconSelector = '.vs__spinner'
	const firstWorkPackageSelector = '.searchInput .vs__dropdown-option'
	const createWorkpackageButtonSelector = '.create-workpackage--button'
	const createWorkPackageNcSelectOptionListSelector = '.create-workpackage-footer-option'
	const createWorkpackageModalSelector = '[data-test-id="create-workpackage-modal"]'
	const noOptionTextSelector = '[role="listbox"] .vs__no-options'

	afterEach(() => {
		wrapper?.unmount()
	})

	describe('state messages', () => {
		it.each([STATE.NO_TOKEN, STATE.ERROR, 'any'])('%s: should display the correct state message', async (state) => {
			wrapper = getMountedWrapper()
			await wrapper.setData({ state })
			expect(wrapper.find(stateSelector).element).toMatchSnapshot()
		})
	})

	describe('work packages select', () => {
		describe('search input', () => {
			let axiosSpy
			afterEach(() => {
				axiosSpy.mockRestore()
			})

			it('should reset the state if search value length becomes lesser than search char limit', async () => {
				axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper()
				const inputField = wrapper.find(inputSelector)
				await wrapper.setData({
					searchResults: [{
						someData: 'someData',
					}],
				})
				await wrapper.setData({
					state: STATE.LOADING,
				})
				await inputField.setValue('a')
				await inputField.setValue('')

				expect(wrapper.vm.searchResults).toMatchObject([])
				expect(wrapper.vm.state).toBe(STATE.OK)
			})
			it.each([
				{
					search: '',
					expectedCallCount: 0,
				},
				{
					search: 'o',
					expectedCallCount: 1,
				},
				{
					search: 'or',
					expectedCallCount: 1,
				},
			])('should send search request only if the search text is greater than or equal to the search char limit', async ({
				search,
				expectedCallCount,
			}) => {
				axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper()
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue(search)
				expect(axiosSpy).toHaveBeenCalledTimes(expectedCallCount)
			})
			it('should include the search text in the search payload', async () => {
				axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper()
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')

				expect(axiosSpy).toHaveBeenCalledTimes(1)
				expect(axiosSpy).toHaveBeenCalledWith(
					expect.stringContaining('work-packages'),
					{
						params: {
							searchQuery: 'orga',
							isSmartPicker: false,
						},
					},
				)
			})
			it('should log an error on invalid payload', async () => {
				axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([{ id: 123 }]))
				const consoleMock = vi.spyOn(console, 'error')
					.mockImplementationOnce(() => {})
				wrapper = getMountedWrapper()
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')
				await nextTick()
				expect(consoleMock).toHaveBeenCalledWith('could not process work package data')
			})
		})

		describe('search list', () => {
			beforeEach(async () => {
				wrapper = getMountedWrapper()
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
			})
			it('should not be displayed if the search results is empty', async () => {
				await wrapper.setData({
					searchResults: [],
				})
				const ncSelectContent = wrapper.find(workpackagesListSelector)
				toMatchSerializedSnapshot(ncSelectContent.html())
			})
			it('should display correct options list of search results', async () => {
				vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper({ id: 1234, name: 'file.txt' })
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue(' ')
				await wrapper.setData({
					searchResults: workPackagesSearchResponse,
				})
				const ncSelectContent = wrapper.find(workpackagesListSelector)
				expect(ncSelectContent.exists()).toBeTruthy()
				const workPackages = ncSelectContent.findAllComponents(workPackageStubSelector)
				expect(workPackages).toHaveLength(workPackagesSearchResponse.length)
				for (let i = 0; i < workPackagesSearchResponse.length; i++) {
					expect(workPackages[i].props()).toMatchSnapshot()
				}
			})
			it('should not display the "avatar" and "name" if the "assignee" is not present in a work package', async () => {
				await wrapper.setData({
					searchResults: workPackagesSearchResponseNoAssignee,
				})
				const assignee = wrapper.find(assigneeSelector)
				expect(assignee.exists()).toBeFalsy()
			})
			it('should only use the options from the latest search response', async () => {
				vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper({ id: 111, name: 'file.txt' })
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue(' ')
				await wrapper.setData({
					searchResults: workPackageObjectsInSearchResults,
				})
				expect(wrapper.findAll(workPackageStubSelector)).toHaveLength(3)
				const axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse(simpleWorkPackageSearchResponse))
					.mockImplementation(() => sendOCSResponse([]))
				await inputField.setValue('orga')
				await flushPromises()
				await nextTick()

				const workPackages = wrapper.findAllComponents(workPackageStubSelector)
				expect(workPackages).toHaveLength(simpleWorkPackageSearchResponse.length)
				for (let i = 0; i < workPackages.length; i++) {
					expect(workPackages[i].props()).toMatchSnapshot()
				}
			})
			it('should not display work packages that are already linked', async () => {
				wrapper = getMountedWrapper({ id: 111 },
					[
						{
							fileId: 111,
							id: 1,
							subject: 'One',
						},
						{
							fileId: 111,
							id: 13,
							subject: 'Write a software',
						},
					])
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse(workPackageObjectsInSearchResults))
					// any other requests e.g. for types and statuses
					.mockImplementation(() => sendOCSResponse([]))

				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('anything longer than 3 char')
				await flushPromises()
				await nextTick()

				// id no 13 is already in workpackages and also in the response
				// so it should not be visible in the search results
				expect(wrapper.vm.searchResults).toMatchObject(
					[
						{
							assignee: 'System',
							id: 2,
							picture: avatarUrl,
							project: 'Demo project',
							statusCol: '',
							statusTitle: 'In progress',
							subject: 'Organize open source conference',
							typeCol: '',
							typeTitle: 'Phase',
						},
						{
							assignee: 'System',
							id: 5,
							picture: avatarUrl,
							project: 'Demo project',
							statusCol: '',
							statusTitle: 'In progress',
							subject: 'Create a website',
							typeCol: '',
							typeTitle: 'Phase',
						},
					],
				)
			})

			it('should not display work packages that are already in the search results', async () => {
				// this case can happen if multiple search are running in parallel and returning its results
				const axiosSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse(workPackageSearchReqResponse))
					.mockImplementation(() => sendOCSResponse([]))
				await wrapper.setData({
					fileInfo: { id: 111 },
					searchResults: [{
						fileId: 111,
						id: 2,
						subject: 'Organize open source conference',
					}],
				})
				wrapper.vm.$parent.workpackages = []

				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('anything longer than 3 char')
				await flushPromises()
				await nextTick()

				expect(wrapper.vm.searchResults).toMatchObject(
					[
						{
							// this comes from the old search results and not from the response
							id: 2,
							subject: 'Organize open source conference',
						},
						{
							assignee: 'System',
							id: 13,
							picture: avatarUrl,
							project: 'Demo project',
							statusCol: '',
							statusTitle: 'In progress',
							subject: 'Write a software',
							typeCol: '',
							typeTitle: 'Phase',
						},
						{
							assignee: 'System',
							id: 5,
							picture: avatarUrl,
							project: 'Demo project',
							statusCol: '',
							statusTitle: 'In progress',
							subject: 'Create a website',
							typeCol: '',
							typeTitle: 'Phase',
						},
					],
				)
			})
			it.each(
				[
					STATE.NO_TOKEN,
					STATE.ERROR,
					STATE.OK,
				],
			)(
				'should only add work packages to the list in loading state',
				async (state) => {
					const spyGetAdditionalMetaData = vi.spyOn(workpackageHelper, 'getAdditionalMetaData')
					wrapper = getMountedWrapper()
					await wrapper.setData({ state })
					await flushPromises()
					await nextTick()

					await wrapper.vm.processWorkPackages(workPackageSearchReqResponse)

					expect(spyGetAdditionalMetaData).toHaveBeenCalledTimes(0)
					expect(wrapper.vm.searchResults).toMatchObject([])
				})
		})

		describe('loading icon', () => {
			it('should be displayed when the wrapper is in "loading" state', async () => {
				wrapper = getMountedWrapper()
				const loadingIcon = wrapper.find(loadingIconSelector)
				expect(loadingIcon.attributes().style).toBe('display: none;')
				await wrapper.setData({
					state: STATE.LOADING,
				})
				await nextTick()
				expect(wrapper.find(loadingIconSelector).exists()).toBeFalsy()
			})
		})

		describe('click on a workpackage option', () => {
			let axiosGetSpy
			beforeEach(async () => {
				axiosGetSpy = vi.spyOn(axios, 'get')
					.mockImplementationOnce(() => sendOCSResponse([]))
				wrapper = getMountedWrapper({ id: 111, name: 'file.txt' })
				await wrapper.setProps({
					searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				})
				const inputField = wrapper.find(inputSelector)
				await inputField.setValue('orga')
				await wrapper.setData({
					searchResults: [{
						fileId: 111,
						id: 999,
					}],
				})
			})
			afterEach(() => {
				axiosGetSpy.mockRestore()
			})
			it('should emit an action', async () => {
				const ncSelectItem = wrapper.find(firstWorkPackageSelector)
				await ncSelectItem.trigger('click')
				const savedEvent = wrapper.emitted('saved')
				expect(savedEvent).toHaveLength(1)
				expect(savedEvent[0]).toEqual([{ fileId: 111, id: 999 }])
			})
			it('should send a request to link file to workpackage', async () => {
				const postSpy = vi.spyOn(axios, 'post')
					.mockImplementationOnce(() => sendOCSResponse({}))
				const ncSelectItem = wrapper.find(firstWorkPackageSelector)
				await ncSelectItem.trigger('click')
				const body = {
					values: {
						workpackageId: 999,
						fileinfo: [
							{
								id: 111,
								name: 'file.txt',
							},
						],
					},
				}
				expect(postSpy).toBeCalledWith(
					workPackageUrl,
					body,
					{ headers: { 'Content-Type': 'application/json' } },
				)
				postSpy.mockRestore()
			})
			it('should reset the state of the search input', async () => {
				const ncSelectItem = wrapper.find(firstWorkPackageSelector)
				expect(wrapper.vm.searchResults).toHaveLength(1)
				expect(wrapper.find('input').element.value).toBe('orga')
				await ncSelectItem.trigger('click')
				expect(wrapper.vm.searchResults).toHaveLength(0)
				expect(wrapper.find('input').element.value).toBe('')

			})
			it('should show an error when linking fails', async () => {
				const err = new Error()
				err.response = { status: 422 }
				axios.post.mockRejectedValueOnce(err)
				const showErrorSpy = vi.spyOn(dialogs, 'showError')
				const ncSelectItem = wrapper.find(firstWorkPackageSelector)
				await ncSelectItem.trigger('click')
				await nextTick()
				expect(showErrorSpy).toBeCalledTimes(1)
				showErrorSpy.mockRestore()
			})
		})

		describe('fileInfo prop', () => {
			it('should reset the input state when the prop is changed', async () => {
				wrapper = getMountedWrapper({ id: 111, name: 'file.txt' }, [], {
					searchResults: [{
						id: 999,
					}],
					selectedId: ['999'],
					state: STATE.LOADING,
				})
				await wrapper.setProps({
					fileInfo: { id: 222, name: 'file2.txt' },
				})
				const inputField = wrapper.find(inputSelector)
				expect(inputField.element.value).toBe('')
				expect(wrapper.vm.searchResults).toMatchObject([])
				expect(wrapper.vm.state).toBe(STATE.OK)
			})
		})
	})

	describe('search with smartpicker', () => {
		let axiosGetSpy
		beforeEach(async () => {
			axiosGetSpy = vi.spyOn(axios, 'get')
				.mockImplementationOnce(() => sendOCSResponse([]))
			wrapper = getMountedWrapper()
			const inputField = wrapper.find(inputSelector)
			await inputField.setValue('orga')
			await wrapper.setData({
				searchResults: [{
					id: 999,
					projectId: 1,
				}],
				openprojectUrl: 'https://openproject.com',
			})
			await nextTick()
			await wrapper.setProps({
				isSmartPicker: true,
			})
			await nextTick()
		})
		afterEach(() => {
			axiosGetSpy.mockRestore()
		})
		it('should emit an action', async () => {
			const ncSelectItem = wrapper.find(firstWorkPackageSelector)
			await ncSelectItem.trigger('click')
			const savedEvent = wrapper.emitted('submit')
			expect(savedEvent).toHaveLength(1)
			expect(savedEvent[0][0]).toEqual('https://openproject.com/wp/999')
		})

		it('should not send a request to link file to workpackage', async () => {
			const postSpy = vi.spyOn(axios, 'post')
				.mockImplementationOnce(() => sendOCSResponse({}))
			const ncSelectItem = wrapper.find(firstWorkPackageSelector)
			await ncSelectItem.trigger('click')
			expect(postSpy).not.toBeCalled()
			postSpy.mockRestore()
		})
	})

	describe('search from multiple files link modal', () => {
		const singleFileInfo = [{
			id: 123,
			name: 'logo.png',
		}]

		const multipleFileInfos = [{
			id: 123,
			name: 'logo.png',
		},
		{
			id: 456,
			name: 'pogo.png',
		}]

		it.each([
			[
				'should set no option text to "Start typing to search" for empty search',
				{
					searchQuery: ' ',
					expectedNoOptionText: 'Start typing to search',
				},
			],
			[
				'should set no option text to "There were no workpackages found" for search query not matched',
				{
					searchQuery: 'query-not-matched',
					expectedNoOptionText: 'No matching work packages found',
				},
			],
		])('%s', async (name, expectedDetails) => {
			wrapper = getMountedWrapper(singleFileInfo)
			await wrapper.setProps({
				searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
			})
			vi.spyOn(axios, 'get')
				.mockImplementationOnce(() => sendOCSResponse([]))
			const inputField = wrapper.find(inputSelector)
			await inputField.setValue(expectedDetails.searchQuery)
			await nextTick()
			const noOptionText = wrapper.find(noOptionTextSelector)
			expect(noOptionText.isVisible()).toBe(true)
			expect(noOptionText.text()).toBe(expectedDetails.expectedNoOptionText)
		})

		describe('single file selected', () => {
			describe('select a work package for linking', () => {
				let axiosGetSpy
				beforeEach(async () => {
					axiosGetSpy = vi.spyOn(axios, 'get')
						.mockImplementationOnce(() => sendOCSResponse([]))
					wrapper = getMountedWrapper(singleFileInfo)
					await wrapper.setProps({
						searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL,
					})
					const inputField = wrapper.find(inputSelector)
					await inputField.setValue('orga')
					await wrapper.setData({
						searchResults: [{
							fileId: 123,
							id: 999,
						}],
					})
				})
				afterEach(() => {
					axiosGetSpy.mockRestore()
				})
				it('should send a request to link file to workpackage', async () => {
					const postSpy = vi.spyOn(axios, 'post')
						.mockImplementationOnce(() => sendOCSResponse({}))
					const ncSelectItem = wrapper.find(firstWorkPackageSelector)
					await ncSelectItem.trigger('click')
					const body = {
						values: {
							workpackageId: 999,
							fileinfo: singleFileInfo,
						},
					}
					expect(postSpy).toBeCalledWith(
						workPackageUrl,
						body,
						{ headers: { 'Content-Type': 'application/json' } },
					)
					postSpy.mockRestore()
				})

				it('should show an error when linking fails', async () => {
					const err = new Error()
					err.response = { status: 422 }
					axios.post.mockRejectedValueOnce(err)
					const showErrorSpy = vi.spyOn(dialogs, 'showError')
					const ncSelectItem = wrapper.find(firstWorkPackageSelector)
					await ncSelectItem.trigger('click')
					await nextTick()
					expect(showErrorSpy).toBeCalledTimes(1)
					showErrorSpy.mockRestore()
				})

				it('should not display work packages that are already linked', async () => {
					const axiosSpy = vi.spyOn(axios, 'get')
						.mockImplementationOnce(() => sendOCSResponse(workPackageSearchReqResponse))
						.mockImplementation(() => sendOCSResponse([]))
					await wrapper.setProps({
						linkedWorkPackages: [{
							fileId: 123,
							id: 2,
							subject: 'Organize open source conference',
						}],
					})

					const inputField = wrapper.find(inputSelector)
					await inputField.setValue('anything longer than 3 char')
					await flushPromises()
					await nextTick()

					expect(wrapper.vm.searchResults).toMatchObject(
						[
							{
								assignee: 'System',
								id: 13,
								picture: avatarUrl,
								project: 'Demo project',
								statusCol: '',
								statusTitle: 'In progress',
								subject: 'Write a software',
								typeCol: '',
								typeTitle: 'Phase',
							},
							{
								assignee: 'System',
								id: 5,
								picture: avatarUrl,
								project: 'Demo project',
								statusCol: '',
								statusTitle: 'In progress',
								subject: 'Create a website',
								typeCol: '',
								typeTitle: 'Phase',
							},
						],
					)
				})

			})

			describe('multiple files selected', () => {
				describe('less than 20', () => {
					describe('select a work package for linking', () => {
						let axiosGetSpy
						beforeEach(async () => {
							axiosGetSpy = vi.spyOn(axios, 'get')
								.mockImplementationOnce(() => sendOCSResponse([]))
							wrapper = getMountedWrapper(multipleFileInfos)
							await wrapper.setProps({
								searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL,
							})
							const inputField = wrapper.find(inputSelector)
							await inputField.setValue('orga')
							await wrapper.setData({
								searchResults: [{
									fileId: 123,
									id: 999,
								}],
							})
						})
						afterEach(() => {
							axiosGetSpy.mockRestore()
						})
						it('should send a request to link file to workpackage', async () => {
							const postSpy = vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							const body = {
								values: {
									workpackageId: 999,
									fileinfo: multipleFileInfos,
								},
							}
							expect(postSpy).toBeCalledWith(
								workPackageUrl,
								body,
								{ headers: { 'Content-Type': 'application/json' } },
							)
							postSpy.mockRestore()
						})

						it('should show an error when linking fails', async () => {
							const err = new Error()
							err.response = { status: 422 }
							axios.post.mockRejectedValueOnce(err)
							const showErrorSpy = vi.spyOn(dialogs, 'showError')
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await nextTick()
							expect(showErrorSpy).toBeCalledTimes(1)
							showErrorSpy.mockRestore()
						})

						it('should display work packages that are already linked', async () => {
							const axiosSpy = vi.spyOn(axios, 'get')
								.mockImplementationOnce(() => sendOCSResponse(workPackageSearchReqResponse))
								.mockImplementation(() => sendOCSResponse([]))
							await wrapper.setProps({
								// here already linked work package is empty when the selected files is more than 1
								linkedWorkPackages: [],
							})

							const inputField = wrapper.find(inputSelector)
							await inputField.setValue('anything longer than 3 char')
							await flushPromises()
							await nextTick()

							expect(wrapper.vm.searchResults).toMatchObject(
								[
									{
										assignee: 'System',
										id: 2,
										picture: avatarUrl,
										project: 'Demo project',
										statusCol: '',
										statusTitle: 'In progress',
										subject: 'Organize open source conference',
										typeCol: '',
										typeTitle: 'Phase',
									},
									{
										assignee: 'System',
										id: 13,
										picture: avatarUrl,
										project: 'Demo project',
										statusCol: '',
										statusTitle: 'In progress',
										subject: 'Write a software',
										typeCol: '',
										typeTitle: 'Phase',
									},
									{
										assignee: 'System',
										id: 5,
										picture: avatarUrl,
										project: 'Demo project',
										statusCol: '',
										statusTitle: 'In progress',
										subject: 'Create a website',
										typeCol: '',
										typeTitle: 'Phase',
									},
								],
							)
						})
					})
				})

				describe('more than 20 with chunk', () => {
					/*
						For the test of linking multiple files more than 20.
						This test scenario creates a file information of 55 which is used through the whole test for the link with chunking.
						It means the file will get chunked as [20, 20, 15].
					 */
					const multipleFilesForChunking = []
					for (let i = 1; i <= 55; i++) {
						multipleFilesForChunking.push({
							id: i,
							name: `test${i}.txt`,
						})
					}

					describe('select a work package for linking', () => {
						let axiosGetSpy
						beforeEach(async () => {
							axiosGetSpy = vi.spyOn(axios, 'get')
								.mockImplementationOnce(() => sendOCSResponse([]))
							wrapper = getMountedWrapper(multipleFilesForChunking)
							await wrapper.setProps({
								searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.LINK_MULTIPLE_FILES_MODAL,
							})
							const inputField = wrapper.find(inputSelector)
							await inputField.setValue('orga')
							await wrapper.setData({
								searchResults: [{
									fileId: 123,
									id: 999,
								}],
							})
						})
						afterEach(() => {
							axiosGetSpy.mockRestore()
							axios.post.mockRestore()
						})
						it('should send request 3 times to link chunked file to workpackage', async () => {
							const postSpy = vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							expect(postSpy).toHaveBeenCalledTimes(3)
						})

						it('should emit event "set-chunked-informations" for 3 times', async () => {
							vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							expect(wrapper.emitted()['set-chunked-informations']).toHaveLength(3)
						})

						it('should link all the files with chunks upon success', async () => {
							vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							const eventEmitted = wrapper.emitted()['set-chunked-informations']
							expect(eventEmitted).toHaveLength(3)
							// here when the linking files with chunking is successful "totalFilesAlreadyLinked" and the total no of files selected must be equal
							const emittedData = eventEmitted.pop().pop()
							expect(emittedData.totalFilesAlreadyLinked).toBe(multipleFilesForChunking.length)
						})

						it.each([
							[
								'should set chunk error true',
								{
									key: 'error',
									value: true,
								},
							],
							[
								'should set alreadylinked files to 40',
								{
									key: 'totalFilesAlreadyLinked',
									value: 40,
								},
							],
							[
								'should set files not linked to 2',
								{
									key: 'totalFilesNotLinked',
									value: 15,
								},
							],
						])('%s when request fails', async (name, expectedData) => {
							// rejects the 3rd request
							vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
								.mockImplementationOnce(() => sendOCSResponse({}))
								.mockImplementation(() => Promise.reject(
									new Error('Throw error'),
								))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							const emittedData = wrapper.emitted()['set-chunked-informations'].pop().pop()
							const expectedKey = expectedData.key
							expect(emittedData[expectedKey]).toBe(expectedData.value)
						})

						it('should set length of remaining files to 15', async () => {
							// rejects the 3rd request
							vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => sendOCSResponse({}))
								.mockImplementationOnce(() => sendOCSResponse({}))
								.mockImplementation(() => Promise.reject(new Error('Throw eror')))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							const emittedData = wrapper.emitted()['set-chunked-informations'].pop().pop()
							expect(emittedData.remainingFileInformations).toHaveLength(15)
						})

						it('should retry once if a request to link fails', async () => {
							const postSpy = vi.spyOn(axios, 'post')
								.mockImplementationOnce(() => {
									throw new Error('Throw error to retry once')
								})
								.mockImplementation(() => sendOCSResponse({}))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							// here 'makeRequestToLinkFilesToWorkPackage' is called 4 times since the chunk is [20,20,15] 3 times and 1 is added for retry since it fails for the first time
							expect(postSpy).toHaveBeenCalledTimes(4)
						})

						it('should not retry again if the retry it self fails', async () => {
							const postSpy = vi.spyOn(axios, 'post')
								.mockImplementation(() => Promise.reject(new Error('Throw eror')))
							const ncSelectItem = wrapper.find(firstWorkPackageSelector)
							await ncSelectItem.trigger('click')
							await flushPromises()
							await nextTick()

							// here the post is called 2 times (1 extra for retry)
							expect(postSpy).toHaveBeenCalledTimes(2)
						})
					})
				})
			})

		})
	})

	describe('create work package button at the footer of the NcSelect', () => {
		const wrapper = getMountedWrapper()
		it('should open create work package modal when clicked', async () => {
			await wrapper.setData({
				isSmartPicker: false,
				state: STATE.OK,
			})
			const button = wrapper.find(createWorkpackageButtonSelector)
			await button.trigger('click')
			expect(wrapper.find(createWorkpackageModalSelector).isVisible()).toBeTruthy()
		})
	})

	describe('create work package option at the footer of the NcSelect option list', () => {
		wrapper = getMountedWrapper()
		it('should open create work package modal when clicked', async () => {
			vi.spyOn(axios, 'get')
				.mockImplementationOnce(() => sendOCSResponse([]))
			wrapper = getMountedWrapper({ id: 1234, name: 'file.txt' })
			await wrapper.setProps({
				searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
			})
			const inputField = wrapper.find(inputSelector)
			await inputField.setValue(' ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
				isSmartPicker: false,
				state: STATE.OK,
			})
			await nextTick()
			const optionList = wrapper.find(createWorkPackageNcSelectOptionListSelector)
			await optionList.trigger('click')
			expect(wrapper.find(createWorkpackageModalSelector).isVisible()).toBeTruthy()
		})
	})

	describe('create work packages event handling', () => {
		beforeEach(async () => {
			wrapper = getMountedWrapper()
			dialogs.showSuccess.mockReset()
			dialogs.showError.mockReset()
		})
		afterEach(async () => {
			wrapper.unmount()
		})
		it('should show an error message if work package creation process gets canceled', () => {
			dialogs.showError.mockImplementationOnce()
			const workpackageCreationEventData = {
				openProjectEventName: 'work_package_creation_cancellation',
			}
			wrapper.vm.onCreateWorkPackageEvent(workpackageCreationEventData)
			expect(dialogs.showError).toBeCalledTimes(1)
			expect(dialogs.showError).toBeCalledWith('Work package creation was not successful.')
		})

		it('should show a success message and link work package to a file if work package creation process is successful', async () => {
			vi.spyOn(axios, 'post')
				.mockImplementation(() => sendOCSResponse({}))
			vi.spyOn(axios, 'get')
				.mockImplementationOnce(() => sendOCSResponse([{
					fileId: 1234,
					id: 1,
					subject: 'Organize open source conference',
				}]))
			// mock this method because we don't really care about this for this test
			vi.spyOn(workpackageHelper, 'getAdditionalMetaData')
				.mockImplementationOnce(() => Promise.resolve(workPackagesSearchResponse))

			dialogs.showSuccess
				.mockImplementation()
			const workpackageCreationEventData = {
				openProjectEventName: 'work_package_creation_success',
				openProjectEventPayload: {
					workPackageId: '1',
				},
			}
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
				newWorkpackageCreated: true,
			})
			await wrapper.setProps({
				searchOrigin: WORKPACKAGES_SEARCH_ORIGIN.PROJECT_TAB,
				fileInfo: { id: 1234, name: 'file.txt' },
			})
			await nextTick()

			wrapper.vm.onCreateWorkPackageEvent(workpackageCreationEventData)
			await flushPromises()
			await nextTick()

			expect(dialogs.showSuccess).toBeCalledTimes(2)
			expect(dialogs.showSuccess).toBeCalledWith('Work package created successfully.')
			expect(dialogs.showSuccess).toBeCalledWith('Link to work package created successfully!')
		})
	})
})

function sendOCSResponse(data, status = 200) {
	return Promise.resolve({
		status,
		data: { ocs: { data } },
	})
}

function getMountedWrapper(fileInfo = {}, linkedWorkPackages = [], data = {}) {
	return mount(SearchInput, {
		global: {
			mocks: {
				t: (msg) => msg,
			},
			stubs: {
				NcAvatar: true,
				WorkPackage: true,
				CreateWorkPackageModal: true,
			},
		},
		data: () => ({
			...data,
		}),
		props: {
			fileInfo,
			linkedWorkPackages,
		},
	})
}
