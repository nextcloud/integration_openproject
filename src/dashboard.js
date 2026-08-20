/**
 * SPDX-FileCopyrightText: 2021-2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import { setupGlobalProperties } from './setup.js'
import Dashboard from './views/Dashboard.vue'

document.addEventListener('DOMContentLoaded', function() {
	OCA.Dashboard.register('openproject_notifications', (el, { widget }) => {
		const app = createApp(Dashboard, {
			title: widget.title,
		})
		setupGlobalProperties(app)
		app.mount(el)
	})

})
