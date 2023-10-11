import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'

let cachedStatusColors = {}
let cachedTypeColors = {}
export const workpackageHelper = {
	// to allow the unit tests, to clear the cache
	clearCache() {
		cachedStatusColors = {}
		cachedTypeColors = {}
	},
	async getColorAttributes(path, id) {
		const url = generateUrl(path + id)
		let response
		try {
			response = await axios.get(url)
		} catch (e) {
			response = e.response
		}
		return (response.status === 200 && response.data?.color)
			? response.data.color
			: ''
	},
	replaceHrefToGetId(href) {
		// this is a helper method replaces the string like this "/api/v3/types/3" to get id
		return href
			? href.replace(/.*\//, '')
			: null
	},
	async getAdditionalMetaData(workPackage, linkableWorkpackage = false) {
		if (typeof workPackage._links.status.href !== 'string'
			|| workPackage._links.status.href === ''
			|| typeof workPackage._links.type.href !== 'string'
			|| workPackage._links.type.href === ''
			|| typeof workPackage.id !== 'number'
			|| typeof workPackage.subject !== 'string'
			|| workPackage.subject === ''
			|| typeof workPackage._links.project.title !== 'string'
			|| workPackage._links.project.title === ''
			|| typeof workPackage._links.status.title !== 'string'
			|| workPackage._links.status.title === ''
			|| typeof workPackage._links.type.title !== 'string'
			|| workPackage._links.type.title === ''
		) {
			throw new Error('missing data in workpackage object')
		}

		if (linkableWorkpackage && typeof workPackage.fileId !== 'number' && workPackage.fileId <= 0) {
			throw new Error('missing data in workpackage object')
		}

		const statusId = this.replaceHrefToGetId(workPackage._links.status.href)
		const typeId = this.replaceHrefToGetId(workPackage._links.type.href)
		const userId = this.replaceHrefToGetId(workPackage._links.assignee.href)
		const projectId = this.replaceHrefToGetId(workPackage._links.project.href)
		const userName = workPackage._links.assignee.title
		const avatarUrl = generateUrl('/apps/integration_openproject/avatar?')
			+ encodeURIComponent('userId')
			+ '=' + userId
			+ '&' + encodeURIComponent('userName')
			+ '=' + userName
		let statusColor
		if (cachedStatusColors[statusId] === undefined) {
			statusColor = await this.getColorAttributes('/apps/integration_openproject/statuses/', statusId)
			cachedStatusColors[statusId] = statusColor
		} else {
			statusColor = cachedStatusColors[statusId]
		}

		let typeColor
		if (cachedTypeColors[typeId] === undefined) {
			typeColor = await this.getColorAttributes('/apps/integration_openproject/types/', typeId)
			cachedTypeColors[typeId] = typeColor
		} else {
			typeColor = cachedTypeColors[typeId]
		}

		return {
			id: workPackage.id,
			subject: workPackage.subject,
			project: workPackage._links.project.title,
			projectId,
			statusTitle: workPackage._links.status.title,
			typeTitle: workPackage._links.type.title,
			assignee: userName,
			statusCol: statusColor,
			typeCol: typeColor,
			picture: avatarUrl,
			fileId: workPackage.fileId,
		}
	},
	chunkMultipleSelectedFilesInformation(fileInformation) {
		// this function chunks 20 files information in an array and returns array of it
		const chunkedResult = []
		for (let i = 0; i < fileInformation.length; i += 20) {
			chunkedResult.push(fileInformation.slice(i, i + 20))
		}
		return chunkedResult
	},
	getTotalNoOfFilesInAlreadyChunkedFilesInformation(chunkedFilesInformations) {
		const totalFiles = []
		for (let i = 0; i < chunkedFilesInformations.length; i++) {
			totalFiles.push(...chunkedFilesInformations[i])
		}
		return totalFiles.length
	},

	/*
		Here the chunkedFilesInformation is the array that contains the array of chunked files information in 20
	*/
	async linkMultipleFilesToWorkPackageWithChunking(chunkedFilesInformations, selectedWorkpackage, isRemaining, component) {
		const chunkingInformation = {
			totalNoOfFilesSelected: this.getTotalNoOfFilesInAlreadyChunkedFilesInformation(chunkedFilesInformations),
			totalFilesAlreadyLinked: 0,
			totalFilesNotLinked: this.getTotalNoOfFilesInAlreadyChunkedFilesInformation(chunkedFilesInformations),
			isChunkingError: false,
			remainingFileInformations: [],
			selectedWorkPackage: selectedWorkpackage,
		}
		for (const fileInfoForBody of chunkedFilesInformations) {
			try {
				let retry = 0
				while (retry <= 1) {
					try {
						await this.makeRequestToLinkFilesToWorkPackage(fileInfoForBody, selectedWorkpackage)
						break
					} catch (e) {
						if (retry === 1) {
							throw new Error()
						}
					}
					retry++
				}
				if (chunkedFilesInformations.indexOf(fileInfoForBody) !== chunkedFilesInformations.length - 1) {
					chunkingInformation.totalFilesAlreadyLinked = chunkingInformation.totalFilesAlreadyLinked + 20
				} else {
					// for the last chunked files information we only count the no of files which are not be 20
					chunkingInformation.totalFilesAlreadyLinked = chunkingInformation.totalFilesAlreadyLinked + fileInfoForBody.length
				}
				chunkingInformation.totalFilesNotLinked = chunkingInformation.totalNoOfFilesSelected - chunkingInformation.totalFilesAlreadyLinked
			} catch (e) {
				chunkingInformation.isChunkingError = true
				// when error encounters while chunking we compute the information of files for relinking  again
				for (let i = chunkedFilesInformations.indexOf(fileInfoForBody); i < chunkedFilesInformations.length; i++) {
					chunkingInformation.remainingFileInformations.push(...chunkedFilesInformations[i])
				}
				break
			} finally {
				if (isRemaining) {
					component.getChunkedInformations(chunkingInformation)
				} else {
					component.$emit('get-chunked-informations', chunkingInformation)
				}
			}
		}
	},

	async makeRequestToLinkFilesToWorkPackage(fileInfoForBody, selectedWorkpackage) {
		const config = {
			headers: {
				'Content-Type': 'application/json',
			},
		}
		const url = generateUrl('/apps/integration_openproject/work-packages')
		const body = {
			values: {
				workpackageId: selectedWorkpackage.id,
				fileinfo: fileInfoForBody,
			},
		}
		await axios.post(url, body, config)
	},
	async linkFileToWorkPackageWithSingleRequest(fileInfoForBody, selectedWorkpackage, successMessage, component) {
		try {
			await this.makeRequestToLinkFilesToWorkPackage(fileInfoForBody, selectedWorkpackage)
			component.$emit('saved', selectedWorkpackage)
			showSuccess(successMessage)
		} catch (e) {
			showError(
				t('integration_openproject', 'Failed to link file to work package')
			)
		}
	},
}
