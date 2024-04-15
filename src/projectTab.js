/**
 * @copyright Copyright (c) 2022 Kiran Parajuli <kiran@jankaritech.com>
 *
 * @author Kiran Parajuli <kiran@jankaritech.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'

import './bootstrap.js'
import ProjectsTab from './views/ProjectsTab.vue'

// Init OpenProject Tab Service
if (!window.OCA.OpenProject) {
	window.OCA.OpenProject = {}
}

const View = Vue.extend(ProjectsTab)
let TabInstance = null

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

window.addEventListener('DOMContentLoaded', function() {
	if (OCA.Files && OCA.Files.Sidebar) {
		OCA.Files.Sidebar.registerTab(projectTab)
	}
})
