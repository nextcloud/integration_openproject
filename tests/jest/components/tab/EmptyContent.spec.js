/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import EmptyContent from '../../../../src/components/tab/EmptyContent'
const localVue = createLocalVue()

describe('EmptyContent.vue Test', () => {
	let wrapper
	const emptyContentMessageSelector = '.empty-content--message'
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
			wrapper = getWrapper({ state: cases.state })
			expect(wrapper.find(connectButtonSelector).exists()).toBe(cases.viewed)
		})
	})
	describe('content title', () => {
		it('should not be displayed if the request url is not valid', () => {
			wrapper = getWrapper({ requestUrl: false })
			expect(wrapper.find(emptyContentMessageSelector).exists()).toBe(false)
		})
		it.each([{
			state: 'no-token',
		}, {
			state: 'error',
		}, {
			state: 'connection-error',
		}, {
			state: 'failed-fetching-workpackages',
		}, {
			state: 'ok',
		}])('shows the correct empty message depending on states if the request url is valid', async (cases) => {
			wrapper = getWrapper({ state: cases.state, adminConfigStatus: true })
			expect(wrapper.find(emptyContentMessageSelector).exists()).toBeTruthy()
			expect(wrapper.find(emptyContentMessageSelector)).toMatchSnapshot()
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
			...propsData,
		},
	})
}
