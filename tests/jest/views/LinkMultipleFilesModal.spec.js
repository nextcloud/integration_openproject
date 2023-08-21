/* jshint esversion: 8 */

import { mount, createLocalVue, shallowMount } from '@vue/test-utils'
import LinkMultipleFilesModal from '../../../src/views/LinkMultipleFilesModal.vue'
import * as initialState from '@nextcloud/initial-state'
import { STATE } from '../../../src/utils.js'

jest.mock('@nextcloud/auth')
jest.mock('@nextcloud/axios')
jest.mock('@nextcloud/dialogs')
jest.mock('@nextcloud/l10n', () => ({
	translate: jest.fn((app, msg) => msg),
	getLanguage: jest.fn(),
}))
const localVue = createLocalVue()

describe('LinkMultipleFilesModal.vue', () => {
	const searchInputStubSelector = 'searchinput-stub'
	const ncModalStubSelector = 'ncmodal-stub'
	const loadingIndicatorSelector = '.loading-spinner'
	const emptyContentSelector = '#openproject-empty-content'
	beforeEach(() => {
		jest.useFakeTimers()
		// eslint-disable-next-line no-import-assign,import/namespace
		initialState.loadState = jest.fn(() => true)
	})

	describe('modal', () => {
		it('should open when "show" is set to true', async () => {
			const wrapper = shallowMount(LinkMultipleFilesModal, { localVue })
			await wrapper.setData({
				show: false,
			})
			await localVue.nextTick()
			await wrapper.vm.showModal()
			expect(wrapper.find(ncModalStubSelector).exists()).toBeTruthy()
		})

		it('should close when "show" is set to false', async () => {
			const wrapper = shallowMount(LinkMultipleFilesModal, { localVue })
			await localVue.nextTick()
			await wrapper.vm.closeRequestModal()
			expect(wrapper.find(ncModalStubSelector).exists()).toBeFalsy()
		})
	})

	describe('search input existence in modal', () => {
		let wrapper
		beforeEach(() => {
			wrapper = mountWrapper()
		})
		it('should not exist if admin config is not ok', async () => {
			await wrapper.setData({
				state: STATE.OK,
				isAdminConfigOk: false,
			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeFalsy()
		})
		it.each([
			{ state: STATE.NO_TOKEN },
			{ state: STATE.CONNECTION_ERROR },
			{ state: STATE.ERROR },
		])('should not exist if the wrapper is not in "ok" state', async (cases) => {
			await wrapper.setData({
				state: cases.STATE,
			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeFalsy()
		})

		it('should exist if the admin config is ok and the wrapper is in "ok" state', async () => {
			await wrapper.setData({
				isAdminConfigOk: true,
				state: STATE.OK,
			})
			expect(wrapper.find(searchInputStubSelector).exists()).toBeTruthy()
		})
	})

	describe('loading icon', () => {
		let wrapper
		beforeEach(() => {
			wrapper = mountWrapper()
		})
		it('should show the loading icon during "loading" state', async () => {
			await wrapper.setData({ state: STATE.LOADING })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
		})
		it('should not show the empty content message during "loading" state', async () => {
			await wrapper.setData({ state: STATE.LOADING })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeFalsy()
		})
		it.each([STATE.OK, STATE.ERROR])('should make the loading icon disappear on state change', async (state) => {
			await wrapper.setData({ state: STATE.LOADING })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeTruthy()
			await wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(loadingIndicatorSelector).exists()).toBeFalsy()
		})
	})

	describe('empty content', () => {
		let wrapper
		beforeEach(() => {
			wrapper = mountWrapper()
		})
		it.each([STATE.NO_TOKEN, STATE.ERROR, STATE.OK])('shows the empty message when state is other than loading', async (state) => {
			await wrapper.setData({ state })
			await localVue.nextTick()
			expect(wrapper.find(emptyContentSelector).exists()).toBeTruthy()
		})
	})
})
function mountWrapper() {
	return mount(LinkMultipleFilesModal, {
		localVue,
		attachTo: document.body,
		mocks: {
			t: (app, msg) => msg,
			generateUrl() {
				return '/'
			},
		},
		stubs: {
			SearchInput: true,
			NcModal: true,
			EmptyContent: true,
		},
		data: () => ({
			show: true,
			state: STATE.OK,
			fileInfos: [],
			alreadyLinkedWorkPackage: [],
			isAdminConfigOk: true,
			workPackageIdToFiles: [],
		}),
	})
}
