/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createLocalVue, shallowMount } from '@vue/test-utils'

import WorkPackage from '../../../../src/components/tab/WorkPackage.vue'
import workPackagesSearchResponse from '../../fixtures/workPackagesSearchResponse.json'

const localVue = createLocalVue()

const selectors = {
	wpItemSelector: '.workpackage',
	wpInfoSelector: '.row__workpackage',
}

describe('WorkPackage.vue', () => {
	let wrapper
	describe('old work package id', () => {
		const wp = structuredClone(workPackagesSearchResponse[0])
		wp.displayId = `${wp.id}`
		beforeEach(() => {
			wrapper = shallowMount(WorkPackage, {
				localVue,
				propsData: {
					workpackage: wp,
				},
			})
		})
		it('shows work packages information', async () => {
			const workPackages = wrapper.find(selectors.wpItemSelector)
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages.find(selectors.wpInfoSelector).text()).toBe(`#${wp.displayId} - ${wp.project}`)
			expect(workPackages.element).toMatchSnapshot()
		})

		it('passes displayName, size and url props to NcAvatar but does not pass the user props', () => {
			const avatar = wrapper.findComponent({ name: 'NcAvatar' })
			expect(avatar.exists()).toBe(true)
			expect(avatar.props()).toMatchObject({
				displayName: wp.assignee,
				size: expect.any(Number),
				url: '/server/index.php/apps/integration_openproject/avatar?userId=1&userName=System',
			})
			expect(avatar.props('user')).toBeUndefined()
		})
	})

	describe('new work package id', () => {
		const wp = structuredClone(workPackagesSearchResponse[0])
		wp.displayId = `WP-${wp.id}`

		beforeEach(() => {
			wrapper = shallowMount(WorkPackage, {
				localVue,
				propsData: {
					workpackage: wp,
				},
			})
		})
		it('shows work packages information', async () => {
			const workPackages = wrapper.find(selectors.wpItemSelector)
			expect(workPackages.exists()).toBeTruthy()
			expect(workPackages.find(selectors.wpInfoSelector).text()).toBe(`${wp.displayId} - ${wp.project}`)
			expect(workPackages.element).toMatchSnapshot()
		})
	})
})
