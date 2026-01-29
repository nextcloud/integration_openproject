/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import vueCustomElement from 'vue-custom-element'
import { registerSidebarTab } from '@nextcloud/files'

import './bootstrap.js'
import OpenProjectSvgIcon from '../img/openproject-icon.svg'
import ProjectsTab from './views/ProjectsTab.vue'

Vue.use(vueCustomElement)

const tagName = 'integration_openproject-files-sidebar-tab'

registerSidebarTab({
	id: 'integration_openproject',
	order: 50,
	displayName: t('integration_openproject', 'OpenProject'),
	iconSvgInline: OpenProjectSvgIcon,
	enabled() {
		return true
	},
	tagName,
	onInit: () => {
		if (window.customElements.get(tagName)) {
			// element already defined
			return
		}
		Vue.customElement(tagName, ProjectsTab, {
			shadow: false,
		})
	},
})
