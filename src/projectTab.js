/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2021-2022 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineCustomElement } from 'vue'
import { registerSidebarTab } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { setupGlobalProperties } from './setup.js'
import OpenProjectSvgIcon from '../img/app-dark.svg'
import ProjectsTab from './views/ProjectsTab.vue'
import APP_ID from './constants/appID.js'

const tagName = `${APP_ID}-files-sidebar-tab`
const SidebarTabElement = defineCustomElement(ProjectsTab, {
	shadowRoot: false,
	configureApp(app) {
		setupGlobalProperties(app)
	},
})

registerSidebarTab({
	id: APP_ID,
	order: 50,
	displayName: t(APP_ID, 'OpenProject'),
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
		window.customElements.define(tagName, SidebarTabElement)
	},
})
