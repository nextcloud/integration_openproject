/**
 * SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import '../bootstrap.js'
import { registerFileAction, FileAction, Permission, getSidebar } from '@nextcloud/files'
import OpenProjectIcon from '../../img/app-dark.svg'
import LinkMultipleFilesModal from '../views/LinkMultipleFilesModal.vue'
import Vue from 'vue'

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
const compare = (files) => {
	// store all the file-id in an array and set the file ids
	const fileInfos = []
	for (const file of files) {
		const fileInfo = {
			id: file.fileid,
			name: file.basename,
		}
		fileInfos.push(fileInfo)
	}
	OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].setFileInfos(fileInfos)
	OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].showModal()
}

// registering file action for single file selection
const singleFileAction = new FileAction({
	id: 'integration_openproject-single',
	displayName: () => t('integration_openproject', 'OpenProject'),
	order: 0,
	enabled({ nodes, view }) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length === 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	async exec({ nodes }) {
		const sidebar = getSidebar()
		const [node] = nodes
		try {
			// If the sidebar is already open for the current file, do nothing
			if (sidebar.node?.source === node.source) {
				console.debug('Sidebar already open for this file', { node })
				return null
			}

			sidebar.open(node, 'integration_openproject')
			return null
		} catch (error) {
			console.error('Error while opening sidebar', { error })
			return false
		}
	},
})
registerFileAction(singleFileAction)

// registering file action for multiple file selection
const multipleFileAction = new FileAction({
	id: 'integration_openproject-multiple',
	displayName: () => t('integration_openproject', 'Link to work package'),
	order: 0,
	enabled({ nodes, view }) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length >= 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	async exec({ nodes }) {
		const node = nodes[0]
		console.debug('in the single action handler')
		OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].setFileInfos([{
			id: node.fileid,
			name: node.basename,
		}])
		OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].showModal()
		// to avoid the toast message
		return null
	},
	async execBatch({ nodes }) {
		console.debug('in the multi action handler')
		compare(nodes)
		// to avoid the toast message
		return nodes.map(n => null)
	},
})
registerFileAction(multipleFileAction)

OC.Plugins.register('OCA.Files.FileList', OCA.OpenProject.FilesPlugin)

const modalId = 'multipleFileLinkModal'
const modalElement = document.createElement('div')
modalElement.id = modalId
document.body.append(modalElement)

OCA.OpenProject.LinkMultipleFilesModalVue = new Vue({
	el: modalElement,
	render: h => {
		return h(LinkMultipleFilesModal)
	},
})
