/**
 * SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp, h } from 'vue'
import { registerFileAction, Permission, getSidebar } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

import { setupGlobalProperties } from '../setup.js'
import LinkMultipleFilesModal from '../views/LinkMultipleFilesModal.vue'
import OpenProjectIcon from '../../img/app-dark.svg'
import APP_ID from '../constants/appID.js'

if (!OCA.OpenProject) {
	/**
	 * @namespace
	 */
	OCA.OpenProject = {
		requestOnFileChange: false,
		actionIgnoreLists: [
			'trashbin',
		],
	}
}

const openLinkWorkPackageModal = (fileInfos) => {
	OCA.OpenProject.LinkMultipleFilesModalVue.setFileInfos(fileInfos)
	OCA.OpenProject.LinkMultipleFilesModalVue.showModal()
}

const getSelectedFilesInfo = (files) => {
	// store all the file-id in an array and set the file ids
	const fileInfos = []
	for (const file of files) {
		const fileInfo = {
			id: file.fileid,
			name: file.basename,
		}
		fileInfos.push(fileInfo)
	}
	return fileInfos
}

// registering file context menu to open OpenProject sidebar
const singleFileAction = {
	id: `${APP_ID}-single`,
	displayName: () => t(APP_ID, 'OpenProject'),
	order: 0,
	enabled({ nodes, view }) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length === 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	// register file context menu action
	async exec({ nodes }) {
		const sidebar = getSidebar()
		const [node] = nodes
		try {
			// If the sidebar is already open for the current file, do nothing
			if (sidebar.node?.source === node.source) {
				console.debug('Sidebar already open for this file', { node })
				return null
			}

			sidebar.open(node, APP_ID)
			return null
		} catch (error) {
			console.error('Error while opening sidebar', { error })
			return false
		}
	},
}
registerFileAction(singleFileAction)

// registering file context menu and batch action
// to open modal for linking OpenProject work package
const multipleFileAction = {
	id: `${APP_ID}-multiple`,
	displayName: () => t(APP_ID, 'Link to work package'),
	order: 0,
	enabled({ nodes, view }) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length >= 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	// register file context menu action
	async exec({ nodes }) {
		const node = nodes[0]
		console.debug('single file action handler')
		openLinkWorkPackageModal([{
			id: node.fileid,
			name: node.basename,
		}])
		// to avoid the toast message
		return null
	},
	// register batch action
	async execBatch({ nodes }) {
		console.debug('batch action handler')
		const fileInfos = getSelectedFilesInfo(nodes)
		openLinkWorkPackageModal(fileInfos)
		// to avoid the toast message
		return nodes.map(n => null)
	},
}
registerFileAction(multipleFileAction)

OC.Plugins.register('OCA.Files.FileList', OCA.OpenProject.FilesPlugin)

const modalContainer = document.createElement('div')
modalContainer.id = 'multipleFileLinkModal'
document.body.append(modalContainer)
// mount modal component to the modal container
const modalApp = createApp({
	render: () => h(LinkMultipleFilesModal, {
		ref: (instance) => {
			OCA.OpenProject.LinkMultipleFilesModalVue = instance
		},
	}),
})
setupGlobalProperties(modalApp)
modalApp.mount(modalContainer)
