/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import SearchInput from '../../../../src/components/tab/SearchInput'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'
import workPackagesSearchResponseNoAssignee from '../../fixtures/workPackagesSearchResponseNoAssignee.json'

jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

const localVue = createLocalVue()

describe('SearchInput.vue tests', () => {
	let wrapper
	beforeEach(() => {
		wrapper = shallowMount(SearchInput, {
			localVue,
			mocks: {
				t: (msg) => msg,
				generateUrl() {
					return '/'
				},
			},
		})
	})
	describe('state messages', () => {
		it.each([{
			state: 'no-token',
			message: 'No OpenProject account connected',
		}, {
			state: 'error',
			message: 'Error connecting to OpenProject',
		}, {
			state: 'loading',
			message: 'Wait while we fetch workpackages',
		}, {
			state: 'empty',
			message: 'Cannot find the work-package you are searching for',
		}])('should be displayed depending upon the state', async (cases) => {
			const stateSelector = '.stateMsg'
			await wrapper.setData({
				state: cases.state,
			})
			expect(wrapper.find(stateSelector).exists()).toBeTruthy()
			expect(wrapper.find(stateSelector).text()).toMatch(cases.message)
		})
	})

	describe('work packages', () => {
		const searchListSelector = '.search-list'
		const inputSelector = '#workpackages-search'
		const statusSelector = '.filter-project-type-status__status'
		const typeSelector = '.filter-project-type-status__type'
		const assigneeSelector = '.filter-assignee'
		it('should not be displayed if the length of words in searchbar is less than or equal to three', async () => {
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('org')
			await wrapper.setData({
				searchResults: [],
			})
			expect(wrapper.find(searchListSelector).exists()).toBeFalsy()
		})

		it('should be displayed if the length of words in searchbar is more than three', async () => {
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('organ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
			})
			const searchList = wrapper.find(searchListSelector)
			expect(searchList.exists()).toBeTruthy()
			expect(searchList).toMatchSnapshot()
		})

		it('should not be displayed if the length of words in searchbar decreases from more than 3 to less', async () => {
			let textInput = wrapper.find(inputSelector)
			await textInput.setValue('orga')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
			})
			let searchList = wrapper.find(searchListSelector)
			expect(searchList.exists()).toBeTruthy()
			textInput = wrapper.find(inputSelector)
			await textInput.setValue('org')
			await wrapper.setData({
				searchResults: [],
			})
			searchList = wrapper.find(searchListSelector)
			expect(searchList.exists()).toBeFalsy()
		})

		it('should display correct background color and text for workpackage status and type', async () => {
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('organ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
			})
			const searchList = wrapper.find(searchListSelector)
			const typeCol = wrapper.find(typeSelector)
			const statusCol = wrapper.find(statusSelector)
			expect(searchList.exists()).toBeTruthy()
			expect(typeCol.element.style.color).toBe('red')
			expect(statusCol.element.style.backgroundColor).toBe('blue')

		})

		it('avatar and name should be displayed if assignee is present', async () => {
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('organ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponse,
			})
			const assignee = wrapper.find(assigneeSelector)
			expect(assignee.exists()).toBeTruthy()
			expect(assignee).toMatchSnapshot()
		})

		it('avatar and name not should be displayed if assignee is not present', async () => {
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('organ')
			await wrapper.setData({
				searchResults: workPackagesSearchResponseNoAssignee,
			})
			const assignee = wrapper.find(assigneeSelector)
			expect(assignee.exists()).toBeFalsy()
		})
	})
})
