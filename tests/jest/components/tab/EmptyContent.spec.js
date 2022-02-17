/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import EmptyContent from '../../../../src/components/tab/EmptyContent'
const localVue = createLocalVue()

function getWrapper(propsData = {}) {
	return shallowMount(EmptyContent, {
		localVue,
		mocks: {
			t: (msg) => msg,
		},
		propsData,
	})
}
describe('EmptyContent.vue Test', () => {
	let wrapper
	const emptyContentMessageSelector = '.title'
	const connectButtonSelector = 'oauthconnectbutton-stub'

	it.each([{
		state: 'ok',
		viewed: false,
	}, {
		state: 'no-token',
		viewed: true,
	}, {
		state: 'error',
		viewed: true,
	}])('shows or hides the connect button depending on the state', (cases) => {
		wrapper = shallowMount(EmptyContent, {
			localVue,
			mocks: {
				t: (msg) => msg,
			},
			propsData: {
				state: cases.state,
				requestUrl: 'http://openproject/oauth/',
			},
		})
		wrapper = getWrapper({ state: cases.state })
		expect(wrapper.find(connectButtonSelector).exists()).toBe(cases.viewed)
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
		wrapper = shallowMount(EmptyContent, {
			localVue,
			mocks: {
				t: (msg) => msg,
			},
			propsData: {
				state: cases.state,
				requestUrl: 'http://openproject/oauth/',
			},
		})
		wrapper = getWrapper({ state: cases.state })
		expect(wrapper.find(emptyContentMessageSelector).exists()).toBeTruthy()
		expect(wrapper.find(emptyContentMessageSelector).text()).toMatch(cases.message)
	})
})
