/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import EmptyContent from '../../../../src/components/tab/EmptyContent'
const localVue = createLocalVue()

describe('EmptyContent.vue Test', () => {
	let wrapper
	const emptyContentMessageSelector = '.empty-content--title'
	const connectButtonSelector = 'oauthconnectbutton-stub'

	describe('connect button', () => {
		it.each([{
			state: 'ok',
			viewed: false,
		}, {
			state: 'no-token',
			viewed: true,
		}, {
			state: 'error',
			viewed: true,
		}])('should be displayed depending on the state', (cases) => {
			wrapper = getWrapper({ state: cases.state, adminConfigStatus: true })
			expect(wrapper.find(connectButtonSelector).exists()).toBe(cases.viewed)
		})
	})
	describe('content title', () => {
		it('should not be displayed if the admin config status is not ok', () => {
			wrapper = getWrapper({ adminConfigStatus: false })
			expect(wrapper.find(emptyContentMessageSelector).exists()).toBe(false)
		})
		it.each([{
			state: 'no-token',
			message: 'No OpenProject account connected',
		}, {
			state: 'error',
			message: 'Unexpected Error',
		}, {
			state: 'connection-error',
			message: 'Error connecting to OpenProject',
		}, {
			state: 'failed-fetching-workpackages',
			message: 'Could not fetch work packages from OpenProject',
		}, {
			state: 'ok',
			message: 'No workspaces linked yet',
		}, {
			state: 'something else',
			message: 'invalid state',
		}])('shows the correct empty message depending on states if admin config status is ok', async (cases) => {
			wrapper = getWrapper({ state: cases.state, adminConfigStatus: true })
			expect(wrapper.find(emptyContentMessageSelector).exists()).toBeTruthy()
			expect(wrapper.find(emptyContentMessageSelector).text()).toMatch(cases.message)
		})
	})
})

function getWrapper(propsData = {}) {
	return shallowMount(EmptyContent, {
		localVue,
		mocks: {
			t: (msg) => msg,
		},
		propsData: {
			state: 'ok',
			requestUrl: 'http://openproject/',
			adminConfigStatus: false,
			...propsData,
		},
	})
}
