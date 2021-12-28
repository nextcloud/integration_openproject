import Vue from 'vue'

import './bootstrap'
import ProjectsTab from './components/ProjectsTab'

// Init OpenProject Tab Service
if (!window.OCA.OpenProject) {
	window.OCA.OpenProject = {}
}

const View = Vue.extend(ProjectsTab)
let TabInstance = null

const projectTab = new OCA.Files.Sidebar.Tab({
	id: 'open-project',
	name: t('integration_openproject', 'OpenProject'),
	icon: 'icon-projects',

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
