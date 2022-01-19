/* jshint esversion: 8 */

import { shallowMount, createLocalVue } from '@vue/test-utils'
import ProjectsTab from '../../../src/views/ProjectsTab'
import axios from '@nextcloud/axios'
jest.mock('@nextcloud/axios')
const localVue = createLocalVue()

describe('ProjectsTab.vue Test', () => {
	let wrapper
	const loadingIndicatorSelector = '.icon-loading'
	const emptyContentSelector = '#openproject-empty-content'
	beforeEach(() => {
		wrapper = shallowMount(ProjectsTab, { localVue })
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
		it.each(['ok', 'error'])('shows the loading icon disappears on state change', async (state) => {
			wrapper.setData({ state: 'loading' })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
			wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeFalsy()
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
			{ responseData: [], AppState: 'ok' },
			{ responseData: 'string', AppState: 'error' },
			{ responseData: null, AppState: 'error' },
			{ responseData: undefined, AppState: 'error' },
		])('sets states according to response content in case of success', async (cases) => {
			axios.get.mockImplementationOnce(() =>
				Promise.resolve({
					data: cases.responseData,
				}),
			)
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(cases.AppState)
		})
		it.each([
			{ HTTPStatus: 400, AppState: 'error' },
			{ HTTPStatus: 401, AppState: 'no-token' },
			{ HTTPStatus: 404, AppState: 'error' },
			{ HTTPStatus: 500, AppState: 'error' },
		])('sets states according to HTTP error codes', async (cases) => {
			const err = new Error()
			err.response = { status: cases.HTTPStatus }
			axios.get.mockRejectedValueOnce(err)
			await wrapper.vm.update({ id: 123 })
			expect(wrapper.vm.state).toBe(cases.AppState)
		})
	})
})
