/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import EmptyContent from '../../../../src/components/tab/EmptyContent'
const localVue = createLocalVue()

describe('EmptyContent.vue Test', () => {
	let wrapper
	const emptyContentMessageSelector = '.empty-content--title'
	const connectButtonSelector = 'oauthconnectbutton-stub'

	describe('connect button', () => {
		it('should not be displayed if the "showConnect" prop is set as false', () => {
			wrapper = getWrapper({ showConnect: false })
			expect(wrapper.find(connectButtonSelector).exists()).toBe(false)
		})
		it.each([{
			state: 'ok',
			viewed: false,
		}, {
			state: 'no-token',
			viewed: true,
		}, {
			state: 'error',
			viewed: true,
		}])('shows or hides the connect button depending on the state if the "showConnect" prop is set as true', (cases) => {
			wrapper = getWrapper({ state: cases.state, showConnect: true })
			expect(wrapper.find(connectButtonSelector).exists()).toBe(cases.viewed)
		})
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
	}])('shows the correct empty message depending on states', async (cases) => {
		wrapper = getWrapper({ state: cases.state })
		expect(wrapper.find(emptyContentMessageSelector).exists()).toBeTruthy()
		expect(wrapper.find(emptyContentMessageSelector).text()).toMatch(cases.message)
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
			showConnect: false,
			...propsData,
		},
	})
}
