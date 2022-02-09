/* jshint esversion: 8 */
import { shallowMount, mount, createLocalVue } from '@vue/test-utils'
import SearchInput from '../../../../src/components/tab/SearchInput'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'

jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

const localVue = createLocalVue()

describe('test search input', () => {
	describe('state error', () => {
		it('should be displayed when state is at error', async () => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			let errorSelector = wrapper.find('.title')
			expect(errorSelector.exists()).toBeFalsy()
			await wrapper.setData({
				state: 'error',
			})
			errorSelector = wrapper.find('.error')
			expect(errorSelector.exists()).toBeTruthy()
		})

		it.each([
			{ state: 'loading' },
			{ state: 'no-token' },
		])('should not be displayed when state is not at error', async (cases) => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			await wrapper.setData({
				state: cases.state,
			})
			const errorSelector = wrapper.find('.error')
			expect(errorSelector.exists()).toBeFalsy()
		})

	})

	describe('state loading', () => {
		it('should be displayed when state is at loading', async () => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			await wrapper.setData({
				state: 'loading',
			})
			const loadingSelector = wrapper.find('.icon-loading')
			expect(loadingSelector.exists()).toBeTruthy()
		})

		it.each([
			{ state: 'error' },
			{ state: 'no-token' },
		])('should not be displayed when state is not at loading', async (cases) => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			await wrapper.setData({
				state: cases.state,
			})
			const loadingSelector = wrapper.find('.icon-loading')
			expect(loadingSelector.exists()).toBeFalsy()
		})

	})

	describe('state no-token', () => {
		it('should be displayed when state is at no-token', async () => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			await wrapper.setData({
				state: 'no-token',
			})
			const loadingSelector = wrapper.find('.noToken')
			expect(loadingSelector.exists()).toBeTruthy()
		})

		it.each([
			{ state: 'error' },
			{ state: 'loading' },
		])('should not be displayed when state is not at loading', async (cases) => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			await wrapper.setData({
				state: cases.state,
			})
			const noTokenSelector = wrapper.find('.noToken')
			expect(noTokenSelector.exists()).toBeFalsy()
		})
	})

	describe('work packages', () => {
		it('should not be displayed if the length of words in searchbar is less than three', async () => {
			const wrapper = mount(SearchInput, {
				localVue,
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			const textInput = wrapper.find('#workpackages-search')
			await textInput.setValue('org')
			await wrapper.setData({
				searchResults: [],
			})
			const searchList = wrapper.find('.search-list')
			expect(searchList.exists()).toBeFalsy()
		})

		it('should be displayed if the length of words in searchbar is more than three', async () => {
			const wrapper = shallowMount(SearchInput, {
				mocks: {
					t: (msg) => msg,
					generateUrl() {
						return '/'
					},
				},
			})
			const textInput = wrapper.find('#workpackages-search')
			await textInput.setValue('organ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
			})
			const searchList = wrapper.find('.search-list')
			expect(searchList.exists()).toBeTruthy()
			expect(wrapper).toMatchSnapshot()
		})
	})
})
