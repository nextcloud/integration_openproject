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
	const { createApp } = await import(/* webpackChunkName: "reference-wp-lazy" */'vue')
	const { default: WorkPackageReferenceWidget } = await import(/* webpackChunkName: "reference-wp-lazy" */'./views/WorkPackageReferenceWidget.vue')

	const widget = createApp(WorkPackageReferenceWidget, {
		richObjectType,
		richObject,
		accessible,
	})
	widget.mixin({ methods: { t, n } })
	widget.mount(el)
})

registerCustomPickerElement('openproject-work-package-ref', async (el, { providerId, accessible }) => {
	const { createApp } = await import(/* webpackChunkName: "reference-picker-lazy" */'vue')
	const { default: WorkPackagePickerElement } = await import(/* webpackChunkName: "reference-picker-lazy" */'./views/WorkPackagePickerElement.vue')

	const app = createApp(WorkPackagePickerElement, {
		providerId,
		accessible,
	})
	app.mixin({ methods: { t, n } })
	const vueElement = app.mount(el)

	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
