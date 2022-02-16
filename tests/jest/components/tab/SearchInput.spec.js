/* jshint esversion: 8 */
import { createLocalVue, mount, shallowMount } from '@vue/test-utils'
import SearchInput from '../../../../src/components/tab/SearchInput'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'
import workPackagesSearchResponseNoAssignee from '../../fixtures/workPackagesSearchResponseNoAssignee.json'

jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(() => ''),
}))

const localVue = createLocalVue()

function mountSearchInput() {
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
	})
}

describe('SearchInput.vue tests', () => {
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
		}])('should be displayed depending upon the state', async (cases) => {
			const stateSelector = '.stateMsg'
			const wrapper = shallowMount(SearchInput, {
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
			expect(wrapper.find(stateSelector).exists()).toBeTruthy()
			expect(wrapper.find(stateSelector).text()).toMatch(cases.message)
		})
	})

	describe('work packages', () => {
		const searchListSelector = '.searchList'
		const inputSelector = '.multiselect__input'
		const statusSelector = '.filterProjectTypeStatus__status'
		const typeSelector = '.filterProjectTypeStatus__type'
		const assigneeSelector = '.filterAssignee'
		it('should not be displayed if the length of words in searchbar is less than or equal to three', async () => {
			const wrapper = mountSearchInput()
			const textInput = wrapper.find(inputSelector)
			await textInput.setValue('org')
			await wrapper.setData({
				searchResults: [],
			})
			expect(wrapper.find(searchListSelector).exists()).toBeFalsy()
		})

		it('should be displayed if the length of words in searchbar is more than three', async () => {
			jest.spyOn(SearchInput.methods, 'makeSearchRequest').mockImplementation()
			const wrapper = mountSearchInput()
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
			jest.spyOn(SearchInput.methods, 'makeSearchRequest').mockImplementation()
			const wrapper = mountSearchInput()
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
			jest.spyOn(SearchInput.methods, 'makeSearchRequest').mockImplementation()
			const wrapper = mountSearchInput()
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
			jest.spyOn(SearchInput.methods, 'makeSearchRequest').mockImplementation()
			const wrapper = mountSearchInput()
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
			jest.spyOn(SearchInput.methods, 'makeSearchRequest').mockImplementation()
			const wrapper = mountSearchInput()
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
