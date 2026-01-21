/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import './bootstrap.js'
import ProjectsTab from './views/ProjectsTab.vue'
import OpenProjectIcon from '../img/app-dark.svg'

// Init OpenProject Tab Service
if (!window.OCA.OpenProject) {
	window.OCA.OpenProject = {}
}

const View = Vue.extend(ProjectsTab)
let TabInstance = null

// Try to import NC 33+ APIs
let registerSidebarTab
try {
	// Dynamic import for NC 33+ API
	const filesModule = await import('@nextcloud/files')
	registerSidebarTab = filesModule.registerSidebarTab
} catch (e) {
	// NC < 33: API not available
	registerSidebarTab = null
}

// For NC < 33: Use old Tab API
const projectTab = new OCA.Files.Sidebar.Tab({
	id: 'open-project',
	name: t('integration_openproject', 'OpenProject'),
	icon: 'icon-openproject',

	async mount(el, fileInfo, context) {
		if (TabInstance) {
			TabInstance.$destroy()
		}
		TabInstance = new View({
			// Better integration with vue parent component
			parent: context,
		})
		// Only mount after we have all the info we need
		await TabInstance.update(fileInfo)
		TabInstance.$mount(el)
	},
	update(fileInfo) {
		TabInstance.update(fileInfo)
	},
	destroy() {
		TabInstance.$destroy()
		TabInstance = null
	},
})

// Setup custom element for NC 33+ (Vue 2 approach)
function setupCustomElement() {
	const tagName = 'openproject-sidebar-tab'

	if (!window.customElements) {
		return false
	}

	if (window.customElements.get(tagName)) {
		// Already defined
		return true
	}

	try {
		// Custom element wrapper for Vue 2
		class OpenProjectTabElement extends HTMLElement {

			constructor() {
				super()
				this._vueInstance = null
			}

			connectedCallback() {
				// Get props from the element
				const node = this.node || {}

				// Create Vue instance
				this._vueInstance = new View()

				// Mount the Vue component
				this._vueInstance.$mount(this)

				// Update with file info if node is provided
				if (node.fileid) {
					this._vueInstance.update({
						id: node.fileid,
						name: node.basename,
						path: node.path,
						...node,
					})
				}
			}

			disconnectedCallback() {
				if (this._vueInstance) {
					this._vueInstance.$destroy()
					this._vueInstance = null
				}
			}

			// Allow setting node dynamically
			set node(value) {
				this._node = value
				if (this._vueInstance && value && value.fileid) {
					this._vueInstance.update({
						id: value.fileid,
						name: value.basename,
						path: value.path,
						...value,
					})
				}
			}

			get node() {
				return this._node
			}

		}

		window.customElements.define(tagName, OpenProjectTabElement)
		return true
	} catch (e) {
		console.error('Failed to define custom element for OpenProject tab', e)
		return false
	}
}

window.addEventListener('DOMContentLoaded', function() {
	if (OCA.Files && OCA.Files.Sidebar) {
		// Try NC 33+ API first
		if (registerSidebarTab && setupCustomElement()) {
			try {
				registerSidebarTab({
					id: 'open-project',
					order: 90,
					displayName: t('integration_openproject', 'OpenProject'),
					iconSvgInline: OpenProjectIcon,
					tagName: 'openproject-sidebar-tab',
					enabled({ node }) {
						// Enable for all files
						return true
					},
				})
				console.debug('OpenProject: Registered sidebar tab using NC 33+ API')
				return
			} catch (e) {
				console.warn('OpenProject: Failed to register sidebar tab with NC 33+ API, falling back', e)
			}
		}

		// Fallback to old API for NC < 33
		OCA.Files.Sidebar.registerTab(projectTab)
		console.debug('OpenProject: Registered sidebar tab using legacy API')
	}
})
