import { createLocalVue, shallowMount } from '@vue/test-utils'

import WorkPackage from '../../../../src/components/tab/WorkPackage.vue'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'

const localVue = createLocalVue()

describe('WorkPackage.vue', () => {
	let wrapper
	const workPackagesSelector = '.workpackage'
	beforeEach(() => {

		wrapper = shallowMount(WorkPackage, {
			localVue,
			propsData: {
				workpackage: workPackagesSearchResponse[0],
			},
		})
	})
	it('shows work packages information', async () => {
		const workPackages = wrapper.find(workPackagesSelector)
		expect(workPackages.exists()).toBeTruthy()
		expect(workPackages).toMatchSnapshot()

	})
})
