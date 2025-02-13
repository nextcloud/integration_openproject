/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
