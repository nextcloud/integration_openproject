/**
 * SPDX-FileCopyrightText: 2023 Jankari Tech Pvt. Ltd.
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import '../bootstrap.js'
import Vue from 'vue'
import LinkMultipleFilesModal from '../views/LinkMultipleFilesModal.vue'

(function() {
	if (!OCA.OpenProject) {
		/**
		 * @namespace
		 */
		OCA.OpenProject = {
			requestOnFileChange: false,
		}
	}

	/**
	 * @namespace
	 */
	OCA.OpenProject.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		attach(fileList) {
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}
			fileList.registerMultiSelectFileAction({
				name: 'open-project',
				displayName: t('integration_openproject', 'Link to work package'),
				mime: 'all',
				permissions: OC.PERMISSION_READ,
				iconClass: 'icon-openproject',
				action: (selectedFiles) => { this.signExample(selectedFiles) },
			})
		},
		signExample: (selectedFiles) => {
			// store all the file-id in an array and set the file ids
			const fileInfos = []
			for (const file of selectedFiles) {
				const fileInfo = {
					id: file.id,
					name: file.name,
				}
				fileInfos.push(fileInfo)
			}
			OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].setFileInfos(fileInfos)
			OCA.OpenProject.LinkMultipleFilesModalVue.$children[0].showModal()
		},
	}
})()

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
