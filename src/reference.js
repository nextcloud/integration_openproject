/**
 * SPDX-FileCopyrightText: 2023-2024 Jankari Tech Pvt. Ltd.
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// this requires @nextcloud/vue >= 7.9.0
import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue'

// this is required for lazy loading
__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('integration_openproject', 'js/') // eslint-disable-line

// this is where we associate our widget component with the richobjects that we return in the reference provider
registerWidget('integration_openproject_work_package', async (el, { richObjectType, richObject, accessible }) => {
	// here we lazy load the components so it does not slow down the initial page load
	const { default: Vue } = await import(/* webpackChunkName: "reference-wp-lazy" */'vue')
	const { default: WorkPackageReferenceWidget } = await import(/* webpackChunkName: "reference-wp-lazy" */'./views/WorkPackageReferenceWidget.vue')
	Vue.mixin({ methods: { t, n } })
	const Widget = Vue.extend(WorkPackageReferenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('openproject-work-package-ref', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-picker-lazy" */'vue')
	const { default: WorkPackagePickerElement } = await import(/* webpackChunkName: "reference-picker-lazy" */'./views/WorkPackagePickerElement.vue')
	Vue.mixin({ methods: { t, n } })

	const Element = Vue.extend(WorkPackagePickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
