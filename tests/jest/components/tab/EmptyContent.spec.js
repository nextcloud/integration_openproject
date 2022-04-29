/* jshint esversion: 8 */
import { shallowMount, createLocalVue } from '@vue/test-utils'
import EmptyContent from '../../../../src/components/tab/EmptyContent'
import { STATE } from '../../../../src/utils'
const localVue = createLocalVue()

describe('EmptyContent.vue Test', () => {
	let wrapper
	const emptyContentMessageSelector = '.empty-content--message'
	const connectButtonSelector = 'oauthconnectbutton-stub'

	describe('connect button', () => {
		it.each([{
			state: STATE.OK,
			viewed: false,
		}, {
			state: STATE.NO_TOKEN,
			viewed: true,
		}, {
			state: STATE.ERROR,
			viewed: true,
		}, {
			state: STATE.CONNECTION_ERROR,
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
		it.each([
			STATE.NO_TOKEN,
			STATE.ERROR,
			STATE.CONNECTION_ERROR,
			STATE.FAILED_FETCHING_WORKPACKAGES,
			STATE.OK,
		])('shows the correct empty message depending on states if the request url is valid', async (state) => {
			wrapper = getWrapper({ state, adminConfigStatus: true })
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
