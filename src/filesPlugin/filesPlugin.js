/*
 * Copyright (c) 2023 Sagar Gurung <sagar@jankaritech.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import '../bootstrap.js'
import { registerFileAction, FileAction, Permission } from '@nextcloud/files'
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
	id: 'open-project-single',
	displayName: () => t('integration_openproject', 'OpenProject'),
	order: 0,
	enabled(nodes, view) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length === 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	async exec(node, view, dir) {
		window.OCA.Files.Sidebar.setActiveTab('open-project')
		await window.OCA.Files.Sidebar.open(node.path)
		return null
	},
})
registerFileAction(singleFileAction)

// registering file action for multiple file selection
const multipleFileAction = new FileAction({
	id: 'open-project-multiple',
	displayName: () => t('integration_openproject', 'Link to work package'),
	order: 0,
	enabled(nodes, view) {
		// we don't want 'files.public' or any other view
		return view.id === 'files'
			&& nodes.length >= 1
			&& !nodes.some(({ permissions }) => (permissions & Permission.READ) === 0)
	},
	iconSvgInline: () => OpenProjectIcon,
	async exec(node, view, dir) {
		console.debug('in the single action handler')
		OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].setFileInfos([{
			id: node.fileid,
			name: node.basename,
		}])
		OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].showModal()
		// to avoid the toast message
		return null
	},
	async execBatch(nodes, view, dir) {
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
